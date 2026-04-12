<?php
/**
 * Selects the cheapest delivery option from Cart::getDeliveryOptionList (unit-testable).
 *
 * @author    Maxsoft
 * @copyright 2007-2026 Maxsoft
 * @license   https://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

namespace PrestaShop\Module\Lowestshipping\Shipping;

use Carrier;

use function is_array;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Logika wyboru najtańszej opcji z wyniku Cart::getDeliveryOptionList (testowalna bez bazy).
 */
final class LowestShippingCalculator
{
    /**
     * @param array<int|string, mixed> $deliveryOptionList
     *
     * @return array{price: float, carrier_name: string, is_free_shipping: bool}|null
     */
    public static function selectBestDeliveryOption(array $deliveryOptionList, bool $withTax, int $idLang): ?array
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
                $names[] = self::resolveCarrierLabel($instance, $idLang);
            }
        }

        $carrierName = implode(' + ', array_filter($names));

        return [
            'price' => $bestPrice,
            'carrier_name' => $carrierName,
            'is_free_shipping' => (bool) ($bestOption['is_free'] ?? false),
        ];
    }

    public static function resolveCarrierLabel(Carrier $carrier, int $idLang): string
    {
        /** @var mixed $rawName Legacy Carrier::$name may be a multilingual array. */
        $rawName = $carrier->name;
        if (is_array($rawName)) {
            if (isset($rawName[$idLang]) && $rawName[$idLang] !== '') {
                return (string) $rawName[$idLang];
            }

            return (string) reset($rawName);
        }

        return (string) $rawName;
    }
}
