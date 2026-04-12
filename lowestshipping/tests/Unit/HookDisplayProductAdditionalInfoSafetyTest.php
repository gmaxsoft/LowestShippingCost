<?php
/**
 * Safety: hook path must not throw when controller is null (gate only).
 *
 * @author    Maxsoft
 * @copyright 2007-2026 Maxsoft
 * @license   https://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Lowestshipping\Hook\ProductAdditionalInfoHookGate;
use Throwable;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Bezpieczeństwo ścieżki jak w hookDisplayProductAdditionalInfo: wczesne wyjścia bez wyjątków
 * (np. brak kontekstu kontrolera).
 */
final class HookDisplayProductAdditionalInfoSafetyTest extends TestCase
{
    public function testHookGateDoesNotThrowWhenControllerIsNull(): void
    {
        try {
            $passes = ProductAdditionalInfoHookGate::passesController(null);
        } catch (Throwable $e) {
            $this->fail('hookDisplayProductAdditionalInfo polega na bramce; null kontrolera nie może rzucać: ' . $e->getMessage());
        }

        $this->assertFalse($passes);
    }
}
