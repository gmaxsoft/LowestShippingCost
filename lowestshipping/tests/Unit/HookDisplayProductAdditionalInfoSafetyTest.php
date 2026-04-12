<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Lowestshipping\Hook\ProductAdditionalInfoHookGate;
use Throwable;

/**
 * Bezpieczeństwo ścieżki jak w hookDisplayProductAdditionalInfo: wczesne wyjścia bez wyjątków
 * (np. brak kontekstu kontrolera).
 */
final class HookDisplayProductAdditionalInfoSafetyTest extends TestCase
{
    public function testHookNiePowinienRzucacPrzyNullKontrolerze_BramkaZwracaFalse(): void
    {
        try {
            $passes = ProductAdditionalInfoHookGate::passesController(null);
        } catch (Throwable $e) {
            $this->fail('hookDisplayProductAdditionalInfo polega na bramce; null kontrolera nie może rzucać: ' . $e->getMessage());
        }

        $this->assertFalse($passes);
    }
}
