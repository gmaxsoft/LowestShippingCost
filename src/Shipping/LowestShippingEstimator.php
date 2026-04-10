<?php

declare(strict_types=1);

namespace PrestaShop\Module\Lowestshipping\Shipping;

use Address;
use Cart;
use CartRule;
use Configuration;
use Context;
use Country;
use Product;
use State;
use Validate;

/**
 * Simulates a one-line cart and reads native delivery options so carrier rules,
 * weights, dimensions, additional_shipping_cost, combinations, cart rules, tax, etc.
 * stay consistent with checkout (subject to hooks like actionFilterDeliveryOptionList).
 */
final class LowestShippingEstimator
{
    public function estimate(
        Context $context,
        int $idProduct,
        int $idProductAttribute,
        int $defaultCountryId,
        bool $withTax
    ): ?float {
        $product = new Product($idProduct, true, $context->language->id, $context->shop->id);

        if (!Validate::isLoadedObject($product) || !$product->active || $product->is_virtual) {
            return null;
        }

        $addressMeta = $this->resolveDeliveryAddress($context, $defaultCountryId);
        if ($addressMeta === null) {
            return null;
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

            return null;
        }

        $simCart->updateQty(1, $idProduct, $idProductAttribute, null, 'up', (int) $context->shop->id);

        $context->cart = $simCart;

        if (class_exists(CartRule::class)) {
            CartRule::autoAddToCart($context, false);
        }

        $deliveryOptionList = $simCart->getDeliveryOptionList(null, true);

        $context->cart = $oldCart;

        $simCart->delete();
        $this->deleteAddressIfEphemeral($addressMeta);

        return $this->extractLowestPrice($deliveryOptionList, $withTax);
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

    private function extractLowestPrice(array $deliveryOptionList, bool $withTax): ?float
    {
        if ($deliveryOptionList === []) {
            return null;
        }

        $min = null;

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

                if ($min === null || $price < $min) {
                    $min = $price;
                }
            }
        }

        return $min;
    }
}
