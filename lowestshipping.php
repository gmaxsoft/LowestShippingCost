<?php

declare(strict_types=1);

/**
 * 2007-2026 PrestaShop
 *
 * @author    Maxsoft
 * @copyright 2007-2026
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\Module\Lowestshipping\Shipping\LowestShippingEstimator;
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
        $this->version = '2.0.0';
        $this->author = 'Maxsoft';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Lowest shipping estimate', [], 'Modules.Lowestshipping.Admin');
        $this->description = $this->trans(
            'Shows the lowest deliverable shipping cost for the current product using the shop’s native carrier and cart rules logic.',
            [],
            'Modules.Lowestshipping.Admin'
        );
        $this->confirmUninstall = $this->trans('Uninstall this module?', [], 'Modules.Lowestshipping.Admin');

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
            && Configuration::updateValue('LOWESTSHIPPING_ENABLE_VISIBILITY_FILTER', false)
            && Configuration::updateValue('LOWESTSHIPPING_EXCLUDED_PRODUCT_IDS', '')
            && Configuration::updateValue('LOWESTSHIPPING_EXCLUDED_CATEGORY_IDS', '');
    }

    public function uninstall(): bool
    {
        Configuration::deleteByName('LOWESTSHIPPING_DEFAULT_COUNTRY');
        Configuration::deleteByName('LOWESTSHIPPING_PRICE_WITH_TAX');
        Configuration::deleteByName('LOWESTSHIPPING_TEXT_PREFIX');
        Configuration::deleteByName('LOWESTSHIPPING_ENABLE_VISIBILITY_FILTER');
        Configuration::deleteByName('LOWESTSHIPPING_EXCLUDED_PRODUCT_IDS');
        Configuration::deleteByName('LOWESTSHIPPING_EXCLUDED_CATEGORY_IDS');

        return parent::uninstall();
    }

    public function getContent()
    {
        $container = SymfonyContainer::getInstance();
        if ($container === null) {
            return $this->displayError($this->trans('Symfony container is not available.', [], 'Modules.Lowestshipping.Admin'));
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
            'module-lowestshipping-front',
            'modules/' . $this->name . '/views/js/front.js',
            ['position' => 'bottom', 'priority' => 200]
        );
        $this->context->controller->registerStylesheet(
            'module-lowestshipping-front',
            'modules/' . $this->name . '/views/css/front.css',
            ['media' => 'all', 'priority' => 200]
        );
    }

    public function hookDisplayProductAdditionalInfo(array $params): string
    {
        if (!isset($this->context->controller) || $this->context->controller->php_self !== 'product') {
            return '';
        }

        $idProduct = $this->extractProductId($params);
        if ($idProduct <= 0) {
            return '';
        }

        if ($this->shouldHideForProduct($idProduct)) {
            return '';
        }

        $idProductAttribute = (int) Tools::getValue('id_product_attribute');
        if ($idProductAttribute <= 0) {
            $idProductAttribute = (int) Product::getDefaultAttribute($idProduct);
        }

        $defaultCountry = (int) Configuration::get('LOWESTSHIPPING_DEFAULT_COUNTRY');
        $withTax = (bool) Configuration::get('LOWESTSHIPPING_PRICE_WITH_TAX');
        $prefix = (string) Configuration::get('LOWESTSHIPPING_TEXT_PREFIX');

        $estimator = new LowestShippingEstimator();
        $price = $estimator->estimate($this->context, $idProduct, $idProductAttribute, $defaultCountry, $withTax);

        if ($price === null) {
            return '';
        }

        $formatted = Tools::displayPrice($price, $this->context->currency);

        $this->context->smarty->assign([
            'lowestshipping_prefix' => $prefix,
            'lowestshipping_formatted' => $formatted,
            'lowestshipping_price' => $price,
            'lowestshipping_id_product' => $idProduct,
            'lowestshipping_id_product_attribute' => $idProductAttribute,
            'lowestshipping_ajax_url' => $this->context->link->getModuleLink('lowestshipping', 'ajax', [], true),
            'lowestshipping_token' => Tools::getToken(false),
        ]);

        return $this->fetch('module:lowestshipping/views/templates/hook/displayProductAdditionalInfo.tpl');
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

    public function isEstimateHiddenForProduct(int $idProduct): bool
    {
        return $this->shouldHideForProduct($idProduct);
    }

    private function shouldHideForProduct(int $idProduct): bool
    {
        if (!(bool) Configuration::get('LOWESTSHIPPING_ENABLE_VISIBILITY_FILTER')) {
            return false;
        }

        $excludedProducts = (string) Configuration::get('LOWESTSHIPPING_EXCLUDED_PRODUCT_IDS');
        $productIds = $this->parseIdList($excludedProducts);
        if (in_array($idProduct, $productIds, true)) {
            return true;
        }

        $excludedCategories = (string) Configuration::get('LOWESTSHIPPING_EXCLUDED_CATEGORY_IDS');
        $categoryIds = $this->parseIdList($excludedCategories);
        if ($categoryIds === []) {
            return false;
        }

        $productCategories = Product::getProductCategories($idProduct);
        foreach ($productCategories as $cid) {
            if (in_array((int) $cid, $categoryIds, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return int[]
     */
    private function parseIdList(string $raw): array
    {
        $parts = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $parts))));
    }
}
