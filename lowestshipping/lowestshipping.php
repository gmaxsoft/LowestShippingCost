<?php
/**
 * Main module class — lowest shipping estimate on the product page (PrestaShop 9).
 *
 * @author    Maxsoft
 * @copyright 2007-2026 Maxsoft
 * @license   https://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\Module\Lowestshipping\Hook\ProductAdditionalInfoHookGate;
use PrestaShop\Module\Lowestshipping\Shipping\LowestShippingEstimator;
use PrestaShop\Module\Lowestshipping\Shipping\LowestShippingQuoteBuilder;
use PrestaShop\Module\Lowestshipping\Shipping\LowestShippingUnavailableHints;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

class Lowestshipping extends Module
{
    public function __construct()
    {
        $this->name = 'lowestshipping';
        $this->tab = 'shipping_logistics';
        $this->version = '2.1.0';
        $this->author = 'Maxsoft';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Najtańszy koszt dostawy', [], 'Modules.Lowestshipping.Admin');
        $this->description = $this->trans(
            'Pokazuje na karcie produktu najniższy możliwy koszt dostawy z użyciem natywnych przewoźników i reguł koszyka PrestaShop.',
            [],
            'Modules.Lowestshipping.Admin',
        );
        $this->confirmUninstall = $this->trans('Odinstalować ten moduł?', [], 'Modules.Lowestshipping.Admin');

        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => '9.99.99'];
    }

    public function install(): bool
    {
        $defaultCountry = (int) Configuration::get('PS_COUNTRY_DEFAULT');

        return parent::install()
            && $this->registerHook('displayProductAdditionalInfo')
            && $this->registerHook('actionFrontControllerSetMedia')
            && Configuration::updateValue('LOWESTSHIPPING_DEFAULT_COUNTRY', $defaultCountry > 0 ? $defaultCountry : 0)
            && Configuration::updateValue('LOWESTSHIPPING_PRICE_WITH_TAX', true)
            && Configuration::updateValue('LOWESTSHIPPING_TEXT_PREFIX', '')
            && Configuration::updateValue('LOWESTSHIPPING_DESCRIPTION', '')
            && Configuration::updateValue('LOWESTSHIPPING_ENABLE_PRODUCT_PAGE', true);
    }

    public function uninstall(): bool
    {
        Configuration::deleteByName('LOWESTSHIPPING_DEFAULT_COUNTRY');
        Configuration::deleteByName('LOWESTSHIPPING_PRICE_WITH_TAX');
        Configuration::deleteByName('LOWESTSHIPPING_TEXT_PREFIX');
        Configuration::deleteByName('LOWESTSHIPPING_DESCRIPTION');
        Configuration::deleteByName('LOWESTSHIPPING_ENABLE_PRODUCT_PAGE');
        Configuration::deleteByName('LOWESTSHIPPING_ENABLE_VISIBILITY_FILTER');
        Configuration::deleteByName('LOWESTSHIPPING_EXCLUDED_PRODUCT_IDS');
        Configuration::deleteByName('LOWESTSHIPPING_EXCLUDED_CATEGORY_IDS');

        return parent::uninstall();
    }

    public function getContent()
    {
        $container = SymfonyContainer::getInstance();
        if ($container === null) {
            return $this->displayError($this->trans('Kontener Symfony jest niedostępny.', [], 'Modules.Lowestshipping.Admin'));
        }

        $router = $container->get('router');
        Tools::redirectAdmin($router->generate('lowestshipping_configuration'));
    }

    public function hookActionFrontControllerSetMedia(array $params): void
    {
        if (!isset($this->context->controller) || $this->context->controller->php_self !== 'product') {
            return;
        }

        $this->context->controller->registerJavascript(
            'module-lowestshipping',
            'modules/' . $this->name . '/views/js/lowestshipping.js',
            ['position' => 'bottom', 'priority' => 200],
        );
        $this->context->controller->registerStylesheet(
            'module-lowestshipping',
            'modules/' . $this->name . '/views/css/lowestshipping.css',
            ['media' => 'all', 'priority' => 200],
        );
    }

    public function hookDisplayProductAdditionalInfo(array $params): string
    {
        $controller = $this->context->controller ?? null;
        if (!ProductAdditionalInfoHookGate::passesController($controller)) {
            return '';
        }

        if (!ProductAdditionalInfoHookGate::passesModuleEnabled((bool) Configuration::get('LOWESTSHIPPING_ENABLE_PRODUCT_PAGE'))) {
            return '';
        }

        $idProduct = $this->extractProductId($params);
        if (!ProductAdditionalInfoHookGate::passesProductId($idProduct)) {
            return '';
        }

        $idProductAttribute = (int) Tools::getValue('id_product_attribute');
        if ($idProductAttribute <= 0) {
            $idProductAttribute = (int) Product::getDefaultAttribute($idProduct);
        }

        $product = new Product($idProduct, false, $this->context->language->id, $this->context->shop->id);
        if (!ProductAdditionalInfoHookGate::passesProductLoaded(Validate::isLoadedObject($product), (bool) $product->is_virtual)) {
            return '';
        }

        $shipping = $this->getLowestShippingCost($product, $idProductAttribute > 0 ? $idProductAttribute : null, 1);

        $prefix = (string) Configuration::get('LOWESTSHIPPING_TEXT_PREFIX');
        $description = (string) Configuration::get('LOWESTSHIPPING_DESCRIPTION');

        $this->context->smarty->assign([
            'lowestshipping_prefix' => $prefix,
            'lowestshipping_formatted' => $shipping['formatted_price'],
            'lowestshipping_price' => $shipping['price'],
            'lowestshipping_carrier_name' => $shipping['carrier_name'],
            'lowestshipping_carrier_line' => $shipping['carrier_line'],
            'lowestshipping_is_free' => $shipping['is_free_shipping'],
            'lowestshipping_available' => $shipping['available'],
            'lowestshipping_hint' => $shipping['hint_message'],
            'lowestshipping_description' => $description,
            'lowestshipping_id_product' => $idProduct,
            'lowestshipping_id_product_attribute' => $idProductAttribute,
            'lowestshipping_ajax_url' => $this->context->link->getModuleLink('lowestshipping', 'ajax', [], true),
            'lowestshipping_token' => Tools::getToken(false),
        ]);

        return $this->fetch('module:lowestshipping/views/templates/hook/displayproductadditionalinfo.tpl');
    }

    /**
     * Wynik kalkulacji dla żądań AJAX (kombinacje).
     *
     * @return array{
     *   available: bool,
     *   price: float|null,
     *   formatted_price: string,
     *   carrier_name: string,
     *   carrier_line: string,
     *   is_free_shipping: bool,
     *   hint_message: string
     * }
     */
    public function getProductShippingEstimate(int $idProduct, int $idProductAttribute): array
    {
        $product = new Product($idProduct, false, $this->context->language->id, $this->context->shop->id);
        if (!Validate::isLoadedObject($product) || $product->is_virtual) {
            return [
                'available' => false,
                'price' => null,
                'formatted_price' => '',
                'carrier_name' => '',
                'carrier_line' => '',
                'is_free_shipping' => false,
                'hint_message' => '',
            ];
        }

        return $this->getLowestShippingCost(
            $product,
            $idProductAttribute > 0 ? $idProductAttribute : null,
            1,
        );
    }

    private function formatDisplayPrice(float $price): string
    {
        $currency = $this->context->currency;
        if ($currency === null || !Validate::isLoadedObject($currency)) {
            return (string) $price;
        }

        return $this->context->getCurrentLocale()->formatPrice($price, (string) $currency->iso_code);
    }

    /**
     * Kalkulacja najniższego kosztu dostawy przez natywne Cart::getDeliveryOptionList (reguły przewoźników, waga, cena, wymiary, kombinacje).
     *
     * @return array{
     *   available: bool,
     *   price: float|null,
     *   formatted_price: string,
     *   carrier_name: string,
     *   carrier_line: string,
     *   is_free_shipping: bool,
     *   hint_message: string
     * }
     */
    private function getLowestShippingCost(Product $product, int|null $idProductAttribute = null, int $quantity = 1): array
    {
        $id = (int) $product->id;
        $attr = ($idProductAttribute !== null && $idProductAttribute > 0)
            ? $idProductAttribute
            : (int) Product::getDefaultAttribute($id);

        $defaultCountry = (int) Configuration::get('LOWESTSHIPPING_DEFAULT_COUNTRY');
        $withTax = (bool) Configuration::get('LOWESTSHIPPING_PRICE_WITH_TAX');

        $estimator = new LowestShippingEstimator();
        $raw = $estimator->estimateDetailed(
            $this->context,
            $id,
            $attr,
            $quantity,
            $defaultCountry,
            $withTax,
        );

        if ($raw['available'] && $raw['price'] !== null) {
            $carrierLine = $raw['carrier_name'] !== ''
                ? $this->trans('Przewoźnik: %carrier%', ['%carrier%' => $raw['carrier_name']], 'Modules.Lowestshipping.Shop')
                : '';

            return LowestShippingQuoteBuilder::buildAvailableRow(
                (float) $raw['price'],
                $this->formatDisplayPrice((float) $raw['price']),
                (string) $raw['carrier_name'],
                (bool) $raw['is_free_shipping'],
                $carrierLine,
            );
        }

        return LowestShippingQuoteBuilder::unavailableWithHint(
            $this->buildShippingUnavailableHint((string) ($raw['reason'] ?? ''), $id, $attr, $quantity, $defaultCountry, $withTax),
        );
    }

    private function buildShippingUnavailableHint(
        string $reason,
        int $idProduct,
        int $idProductAttribute,
        int $quantity,
        int $defaultCountry,
        bool $withTax,
    ): string {
        $noCarriers = $this->trans('Brak dostępnej opcji dostawy dla tego produktu.', [], 'Modules.Lowestshipping.Shop');
        $checkout = $this->trans('Koszt dostawy zostanie obliczony przy składaniu zamówienia.', [], 'Modules.Lowestshipping.Shop');

        $shippingFrom = null;
        if ($reason === 'no_address') {
            $fallback = (new LowestShippingEstimator())->estimate(
                $this->context,
                $idProduct,
                $idProductAttribute,
                (int) Configuration::get('PS_COUNTRY_DEFAULT') ?: $defaultCountry,
                $withTax,
                $quantity,
            );
            if ($fallback !== null) {
                $shippingFrom = $this->trans(
                    'Koszt dostawy od %price%',
                    ['%price%' => $this->formatDisplayPrice($fallback)],
                    'Modules.Lowestshipping.Shop',
                );
            }
        }

        return LowestShippingUnavailableHints::resolve($reason, $noCarriers, $checkout, $shippingFrom);
    }

    private function extractProductId(array $params): int
    {
        if (isset($params['product'])) {
            $p = $params['product'];
            if (is_array($p) && isset($p['id'])) {
                return (int) $p['id'];
            }
            if (is_array($p) && isset($p['id_product'])) {
                return (int) $p['id_product'];
            }
            if (is_object($p) && isset($p->id_product)) {
                return (int) $p->id_product;
            }
            if (is_object($p) && method_exists($p, 'getId')) {
                return (int) $p->getId();
            }
        }

        return (int) Tools::getValue('id_product');
    }

    public function isProductPageEstimateEnabled(): bool
    {
        return (bool) Configuration::get('LOWESTSHIPPING_ENABLE_PRODUCT_PAGE');
    }
}
