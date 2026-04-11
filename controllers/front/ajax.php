<?php

declare(strict_types=1);

/**
 * 2007-2026 PrestaShop
 *
 * @license https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0
 */

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
                'available' => false,
                'formatted_price' => '',
                'hint' => '',
                'carrier_line' => '',
                'prefix' => '',
                'description' => '',
            ]));

            return;
        }

        $prefix = (string) Configuration::get('LOWESTSHIPPING_TEXT_PREFIX');
        $description = (string) Configuration::get('LOWESTSHIPPING_DESCRIPTION');

        $row = $module->getProductShippingEstimate($idProduct, $idProductAttribute);

        $this->ajaxRender(json_encode([
            'success' => true,
            'available' => $row['available'],
            'price' => $row['price'],
            'formatted_price' => $row['formatted_price'],
            'hint' => $row['hint_message'],
            'carrier_line' => $row['carrier_line'],
            'is_free_shipping' => $row['is_free_shipping'],
            'prefix' => $prefix,
            'description' => $description,
        ]));
    }
}
