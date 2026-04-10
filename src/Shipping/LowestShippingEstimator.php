<?php

declare(strict_types=1);

namespace PrestaShop\Module\Lowestshipping\Shipping;

use Address;
use Carrier;
use Cart;
use CartRule;
use Configuration;
use Context;
use Country;
use Product;
use State;
use Validate;

/**
 * Symuluje koszyk (PrestaShop wymaga krótkotrwałego zapisu wiersza Cart w bazie
 * do wywołania getDeliveryOptionList; rekord jest usuwany zaraz po kalkulacji).
 */
final class LowestShippingEstimator
{
    public function estimate(
        Context $context,
        int $idProduct,
        int $idProductAttribute,
        int $defaultCountryId,
        bool $withTax,
        int $quantity = 1
    ): ?float {
        $detail = $this->estimateDetailed(
            $context,
            $idProduct,
            $idProductAttribute,
            $quantity,
            $defaultCountryId,
            $withTax
        );

        return $detail['available'] ? (float) $detail['price'] : null;
    }

    /**
     * @return array{
     *   available: bool,
     *   price: float|null,
     *   carrier_name: string,
     *   is_free_shipping: bool,
     *   reason: string|null
     * }
     */
    public function estimateDetailed(
        Context $context,
        int $idProduct,
        int $idProductAttribute,
        int $quantity,
        int $defaultCountryId,
        bool $withTax
    ): array {
        $empty = static fn (string $reason): array => [
            'available' => false,
            'price' => null,
            'carrier_name' => '',
            'is_free_shipping' => false,
            'reason' => $reason,
        ];

        $product = new Product($idProduct, true, $context->language->id, $context->shop->id);

        if (!Validate::isLoadedObject($product) || !$product->active) {
            return $empty('invalid_product');
        }

        if ($product->is_virtual) {
            return $empty('virtual');
        }

        if ($quantity < 1) {
            $quantity = 1;
        }

        $addressMeta = $this->resolveDeliveryAddress($context, $defaultCountryId);
        if ($addressMeta === null) {
            return $empty('no_address');
        }

        $oldCart = $context->cart;
        $simCart = new Cart();
        $simCart->id_shop = (int) $context->shop->id;
        $simCart->id_shop_group = (int) $context->shop->id_shop_group;
        $simCart->id_currency = (int) $context->currency->id;
        $simCart->id_lang = (int) $context->language->id;
        $simCart->id_customer = (int) $context->customer->id;
        $simCart->id_guest = (int) $context->cookie->id_guest;
        $simCart->secure_key = $context->customer->isLogged()
            ? $context->customer->secure_key
            : ($context->cart && $context->cart->secure_key
                ? $context->cart->secure_key
                : md5(uniqid('lowestshipping', true) . (string) mt_rand()));
        $simCart->id_address_delivery = $addressMeta['id_address'];
        $simCart->id_address_invoice = $addressMeta['id_address'];

        if (!$simCart->add()) {
            $this->deleteAddressIfEphemeral($addressMeta);

            return $empty('cart_error');
        }

        $simCart->updateQty($quantity, $idProduct, $idProductAttribute, null, 'up', (int) $context->shop->id);

        $context->cart = $simCart;

        if (class_exists(CartRule::class)) {
            CartRule::autoAddToCart($context, false);
        }

        $deliveryOptionList = $simCart->getDeliveryOptionList(null, true);

        $context->cart = $oldCart;

        $simCart->delete();
        $this->deleteAddressIfEphemeral($addressMeta);

        $best = $this->extractBestDeliveryOption($deliveryOptionList, $withTax, (int) $context->language->id);

        if ($best === null) {
            return $empty('no_carriers');
        }

        return [
            'available' => true,
            'price' => $best['price'],
            'carrier_name' => $best['carrier_name'],
            'is_free_shipping' => $best['is_free_shipping'],
            'reason' => null,
        ];
    }

    /**
     * @param array<int|string, mixed> $deliveryOptionList
     *
     * @return array{price: float, carrier_name: string, is_free_shipping: bool}|null
     */
    private function extractBestDeliveryOption(array $deliveryOptionList, bool $withTax, int $idLang): ?array
    {
        if ($deliveryOptionList === []) {
            return null;
        }

        $bestOption = null;
        $bestPrice = null;

        foreach ($deliveryOptionList as $options) {
            if (!is_array($options)) {
                continue;
            }

            foreach ($options as $option) {
                if (!is_array($option)) {
                    continue;
                }

                if (!isset($option['total_price_with_tax'], $option['total_price_without_tax'])) {
                    continue;
                }

                $price = $withTax
                    ? (float) $option['total_price_with_tax']
                    : (float) $option['total_price_without_tax'];

                if ($bestPrice === null || $price < $bestPrice) {
                    $bestPrice = $price;
                    $bestOption = $option;
                }
            }
        }

        if ($bestOption === null || $bestPrice === null) {
            return null;
        }

        $names = [];
        foreach ($bestOption['carrier_list'] ?? [] as $carrierRow) {
            if (!is_array($carrierRow)) {
                continue;
            }

            $instance = $carrierRow['instance'] ?? null;
            if ($instance instanceof Carrier) {
                $names[] = $this->resolveCarrierLabel($instance, $idLang);
            }
        }

        $carrierName = implode(' + ', array_filter($names));

        return [
            'price' => $bestPrice,
            'carrier_name' => $carrierName,
            'is_free_shipping' => (bool) ($bestOption['is_free'] ?? false),
        ];
    }

    private function resolveCarrierLabel(Carrier $carrier, int $idLang): string
    {
        $name = $carrier->name;
        if (is_array($name)) {
            if (isset($name[$idLang]) && $name[$idLang] !== '') {
                return (string) $name[$idLang];
            }

            return (string) reset($name);
        }

        return (string) $name;
    }

    /**
     * @return array{id_address: int, ephemeral: bool}|null
     */
    private function resolveDeliveryAddress(Context $context, int $defaultCountryId): ?array
    {
        if ($context->customer->isLogged()) {
            $idAddress = (int) Address::getFirstCustomerAddressId((int) $context->customer->id);
            if ($idAddress > 0) {
                return ['id_address' => $idAddress, 'ephemeral' => false];
            }
        }

        $idCountry = $defaultCountryId > 0 ? $defaultCountryId : (int) Configuration::get('PS_COUNTRY_DEFAULT');
        if ($idCountry <= 0) {
            return null;
        }

        $address = new Address();
        $address->id_customer = $context->customer->isLogged() ? (int) $context->customer->id : 0;
        $address->id_country = $idCountry;
        $address->alias = 'lowestshipping-estimate';
        $address->firstname = 'Estimate';
        $address->lastname = 'Guest';
        $address->address1 = '-';
        $address->city = '-';
        $address->postcode = '';
        $address->phone = '0000000000';
        $address->phone_mobile = '0000000000';

        $country = new Country($idCountry);
        if (Validate::isLoadedObject($country) && $country->contains_states) {
            $states = State::getStatesByIdCountry($idCountry);
            if (!empty($states)) {
                $address->id_state = (int) $states[0]['id_state'];
            }
        }

        if (!$address->add()) {
            return null;
        }

        return ['id_address' => (int) $address->id, 'ephemeral' => true];
    }

    /**
     * @param array<string, mixed> $addressMeta
     */
    private function deleteAddressIfEphemeral(array $addressMeta): void
    {
        if (empty($addressMeta['ephemeral'])) {
            return;
        }

        $addr = new Address((int) $addressMeta['id_address']);
        if (Validate::isLoadedObject($addr)) {
            $addr->delete();
        }
    }
}
