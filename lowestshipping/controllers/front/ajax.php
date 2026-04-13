<?php
/**
 * Front controller — JSON AJAX for product-page shipping estimate.
 *
 * @author    Maxsoft
 * @copyright 2007-2026 Maxsoft
 * @license   https://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

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
        $quantity = (int) Tools::getValue('quantity');
        if ($quantity <= 0) {
            $quantity = 1;
        }

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

        $row = $module->getProductShippingEstimate($idProduct, $idProductAttribute, $quantity);

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
