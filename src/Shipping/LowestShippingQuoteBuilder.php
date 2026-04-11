<?php

declare(strict_types=1);

namespace PrestaShop\Module\Lowestshipping\Shipping;

/**
 * Mapowanie wyniku estymacji na dane dla szablonu.
 */
final class LowestShippingQuoteBuilder
{
    /**
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
    public static function buildAvailableRow(
        float $price,
        string $formattedPriceHtml,
        string $carrierName,
        bool $isFreeShipping,
        string $carrierLineTranslated
    ): array {
        return [
            'available' => true,
            'price' => $price,
            'formatted_price' => $formattedPriceHtml,
            'carrier_name' => $carrierName,
            'is_free_shipping' => $isFreeShipping,
            'carrier_line' => $carrierLineTranslated,
            'hint_message' => '',
        ];
    }

    /**
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
    public static function unavailableWithHint(string $hint): array
    {
        return [
            'available' => false,
            'price' => null,
            'formatted_price' => '',
            'carrier_name' => '',
            'is_free_shipping' => false,
            'carrier_line' => '',
            'hint_message' => $hint,
        ];
    }
}
