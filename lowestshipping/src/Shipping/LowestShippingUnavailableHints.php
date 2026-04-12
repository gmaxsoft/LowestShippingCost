<?php
/**
 * User-facing hints when no shipping estimate is available (no DB access).
 *
 * @author    Maxsoft
 * @copyright 2007-2026 Maxsoft
 * @license   https://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

namespace PrestaShop\Module\Lowestshipping\Shipping;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Komunikaty przy niedostępnej wycenie (bez wywołań bazy).
 */
final class LowestShippingUnavailableHints
{
    public static function resolve(
        string $reason,
        string $noCarriersMessage,
        string $checkoutMessage,
        ?string $shippingFromMessage,
    ): string {
        if ($reason === 'virtual' || $reason === 'invalid_product') {
            return '';
        }

        if ($reason === 'no_carriers' || $reason === 'cart_error') {
            return $noCarriersMessage;
        }

        if ($reason === 'no_address') {
            return $shippingFromMessage ?? $checkoutMessage;
        }

        return $checkoutMessage;
    }
}
