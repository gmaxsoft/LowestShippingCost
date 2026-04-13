<?php
/**
 * Front controller — JSON kosztu najniższej dostawy (ilość + kombinacja, bez cache).
 * Obsługa dynamicznej zmiany #quantity_wanted na karcie produktu (PrestaShop 9).
 *
 * @author    Maxsoft
 * @copyright 2007-2026 Maxsoft
 * @license   https://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class LowestshippingGetcostModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent(): void
    {
        // Żądanie AJAX — nie renderuj szablonu strony, tylko JSON w display().
        $this->ajax = true;
        parent::initContent();
    }

    public function display(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->isTokenValid()) {
            $this->emitJson([
                'success' => false,
                'message' => 'Nieprawidłowy token bezpieczeństwa.',
            ]);
        }

        $idProduct = (int) Tools::getValue('id_product');
        $idProductAttribute = (int) Tools::getValue('id_product_attribute');
        $quantity = (int) Tools::getValue('quantity');
        if ($quantity <= 0) {
            $quantity = 1;
        }

        if ($idProduct <= 0) {
            $this->emitJson([
                'success' => false,
                'message' => 'Brak lub nieprawidłowy produkt.',
            ]);
        }

        /** @var Lowestshipping $module */
        $module = $this->module;
        if (!$module instanceof Lowestshipping || !$module->isProductPageEstimateEnabled()) {
            $this->emitJson([
                'success' => true,
                'available' => false,
                'cost' => '',
                'formatted' => '',
                'carrier_name' => '',
                'carrier_line' => '',
                'is_free' => false,
                'html' => '',
                'message' => '',
            ]);
        }

        $product = new Product(
            $idProduct,
            false,
            (int) $this->context->language->id,
            (int) $this->context->shop->id,
        );

        if (!Validate::isLoadedObject($product) || $product->is_virtual) {
            $this->emitJson([
                'success' => false,
                'message' => 'Produkt niedostępny lub wirtualny.',
            ]);
        }

        if ($idProductAttribute <= 0) {
            $idProductAttribute = (int) Product::getDefaultAttribute($idProduct);
        }

        $row = $module->getLowestShippingCost(
            $product,
            $idProductAttribute > 0 ? $idProductAttribute : null,
            $quantity,
        );

        $prefix = (string) Configuration::get('LOWESTSHIPPING_TEXT_PREFIX');

        if ($row['available'] && $row['price'] !== null) {
            $price = (float) $row['price'];
            $costPlain = $this->formatCostPlain($price);
            $formatted = strip_tags(html_entity_decode((string) $row['formatted_price'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $html = $this->buildPriceHtml($prefix, $formatted);

            $this->emitJson([
                'success' => true,
                'available' => true,
                'cost' => $costPlain,
                'formatted' => $formatted,
                'carrier_name' => (string) $row['carrier_name'],
                'carrier_line' => (string) $row['carrier_line'],
                'is_free' => (bool) $row['is_free_shipping'],
                'html' => $html,
                'message' => '',
            ]);
        }

        $hint = (string) $row['hint_message'];
        $html = $hint !== ''
            ? '<span id="lowest-shipping-price" class="lowest-shipping-price-value">' . htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') . '</span>'
            : '';

        $this->emitJson([
            'success' => true,
            'available' => false,
            'cost' => '',
            'formatted' => '',
            'carrier_name' => '',
            'carrier_line' => '',
            'is_free' => false,
            'html' => $html,
            'message' => '',
        ]);
    }

    /**
     * Kwota bez symbolu waluty (np. "12,99") — wynik zależy od quantity, nie cache'uj.
     */
    private function formatCostPlain(float $price): string
    {
        return str_replace('.', ',', number_format($price, 2, '.', ''));
    }

    private function buildPriceHtml(string $prefix, string $formattedPlainText): string
    {
        $prefixEsc = htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8');
        $priceEsc = htmlspecialchars($formattedPlainText, ENT_QUOTES, 'UTF-8');

        return '<strong class="lowest-shipping-prefix">' . $prefixEsc . '</strong>'
            . '<span id="lowest-shipping-price" class="lowest-shipping-price-value">' . $priceEsc . '</span>';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function emitJson(array $payload): void
    {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
