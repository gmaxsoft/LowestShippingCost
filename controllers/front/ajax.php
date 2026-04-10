<?php

declare(strict_types=1);

/**
 * 2007-2026 PrestaShop
 *
 * @license https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0
 */

use PrestaShop\Module\Lowestshipping\Shipping\LowestShippingEstimator;

class LowestshippingAjaxModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function displayAjaxLowestshipping(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->isTokenValid()) {
            $this->ajaxRender(json_encode([
                'success' => false,
                'error' => 'bad_token',
            ]));

            return;
        }

        $idProduct = (int) Tools::getValue('id_product');
        $idProductAttribute = (int) Tools::getValue('id_product_attribute');

        if ($idProduct <= 0) {
            $this->ajaxRender(json_encode(['success' => false, 'error' => 'bad_product']));

            return;
        }

        /** @var Lowestshipping $module */
        $module = $this->module;
        if (!$module instanceof Lowestshipping || !$module->isProductPageEstimateEnabled()) {
            $this->ajaxRender(json_encode([
                'success' => true,
                'formatted_price' => '',
                'prefix' => '',
            ]));

            return;
        }

        $defaultCountry = (int) Configuration::get('LOWESTSHIPPING_DEFAULT_COUNTRY');
        $withTax = (bool) Configuration::get('LOWESTSHIPPING_PRICE_WITH_TAX');
        $prefix = (string) Configuration::get('LOWESTSHIPPING_TEXT_PREFIX');

        $estimator = new LowestShippingEstimator();
        $price = $estimator->estimate(
            $this->context,
            $idProduct,
            $idProductAttribute,
            $defaultCountry,
            $withTax
        );

        if ($price === null) {
            $this->ajaxRender(json_encode([
                'success' => true,
                'formatted_price' => '',
                'prefix' => $prefix,
            ]));

            return;
        }

        $this->ajaxRender(json_encode([
            'success' => true,
            'price' => $price,
            'formatted_price' => Tools::displayPrice($price, $this->context->currency),
            'prefix' => $prefix,
        ]));
    }
}
