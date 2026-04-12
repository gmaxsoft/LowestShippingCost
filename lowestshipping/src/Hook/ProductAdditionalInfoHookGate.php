<?php

declare(strict_types=1);

namespace PrestaShop\Module\Lowestshipping\Hook;

/**
 * Warunki wyświetlenia bloku na karcie produktu (testowalne bez pełnego kontekstu PrestaShop).
 */
final class ProductAdditionalInfoHookGate
{
    public static function passesController(?object $controller): bool
    {
        return $controller !== null
            && property_exists($controller, 'php_self')
            && $controller->php_self === 'product';
    }

    public static function passesModuleEnabled(bool $enableProductPage): bool
    {
        return $enableProductPage;
    }

    public static function passesProductId(int $idProduct): bool
    {
        return $idProduct > 0;
    }

    public static function passesProductLoaded(bool $loaded, bool $isVirtual): bool
    {
        return $loaded && !$isVirtual;
    }
}
