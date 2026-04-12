<?php
/**
 * Unit tests for ProductAdditionalInfoHookGate.
 *
 * @author    Maxsoft
 * @copyright 2007-2026 Maxsoft
 * @license   https://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Lowestshipping\Hook\ProductAdditionalInfoHookGate;
use Throwable;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ProductAdditionalInfoHookGateTest extends TestCase
{
    public function testPassesControllerPrzyNullNieRzucaIRezultatJestFalse(): void
    {
        try {
            $ok = ProductAdditionalInfoHookGate::passesController(null);
        } catch (Throwable $e) {
            $this->fail('Bramka nie powinna rzucać wyjątku przy null kontrolera: ' . $e->getMessage());
        }

        $this->assertFalse($ok);
    }

    #[DataProvider('providerKontrolerProduktu')]
    public function testPassesControllerDlaStronyProduktu(
        ?object $controller,
        bool $expected,
    ): void {
        $this->assertSame($expected, ProductAdditionalInfoHookGate::passesController($controller));
    }

    public static function providerKontrolerProduktu(): array
    {
        $product = new class () {
            public string $php_self = 'product';
        };
        $home = new class () {
            public string $php_self = 'index';
        };

        return [
            'produkt' => [$product, true],
            'strona_glowna' => [$home, false],
            'null' => [null, false],
        ];
    }

    #[DataProvider('providerWlaczenieModulu')]
    public function testPassesModuleEnabled(bool $enabled, bool $expected): void
    {
        $this->assertSame($expected, ProductAdditionalInfoHookGate::passesModuleEnabled($enabled));
    }

    public static function providerWlaczenieModulu(): array
    {
        return [
            'wlaczony' => [true, true],
            'wylaczony' => [false, false],
        ];
    }

    #[DataProvider('providerIdProduktu')]
    public function testPassesProductId(int $id, bool $expected): void
    {
        $this->assertSame($expected, ProductAdditionalInfoHookGate::passesProductId($id));
    }

    public static function providerIdProduktu(): array
    {
        return [
            'poprawny' => [5, true],
            'zero' => [0, false],
            'ujemny' => [-1, false],
        ];
    }

    #[DataProvider('providerProduktZaladowanyWirtualny')]
    public function testPassesProductLoaded(bool $loaded, bool $virtual, bool $expected): void
    {
        $this->assertSame($expected, ProductAdditionalInfoHookGate::passesProductLoaded($loaded, $virtual));
    }

    public static function providerProduktZaladowanyWirtualny(): array
    {
        return [
            'fizyczny_zaladowany' => [true, false, true],
            'wirtualny' => [true, true, false],
            'nie_zaladowany' => [false, false, false],
        ];
    }
}
