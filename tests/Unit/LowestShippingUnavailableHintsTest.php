<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Lowestshipping\Shipping\LowestShippingUnavailableHints;

final class LowestShippingUnavailableHintsTest extends TestCase
{
    private const NO = 'no_carriers_msg';
    private const CHECKOUT = 'checkout_msg';

    #[DataProvider('providerPowodyKomunikatu')]
    public function testResolveZwracaOczekiwanyKomunikat(
        string $reason,
        string $expected
    ): void {
        $hint = LowestShippingUnavailableHints::resolve(
            $reason,
            self::NO,
            self::CHECKOUT,
            'from_msg'
        );

        $this->assertSame($expected, $hint);
    }

    public static function providerPowodyKomunikatu(): array
    {
        return [
            'brak_przewoznikow' => ['no_carriers', self::NO],
            'blad_koszyka' => ['cart_error', self::NO],
            'brak_adresu_z_fallback' => ['no_address', 'from_msg'],
            'produkt_wirtualny' => ['virtual', ''],
            'nieprawidlowy_produkt' => ['invalid_product', ''],
            'domyslnie_checkout' => ['heavy_or_other', self::CHECKOUT],
        ];
    }

    public function testResolveNoAddressBezShippingFromUzywaCheckout(): void
    {
        $hint = LowestShippingUnavailableHints::resolve(
            'no_address',
            self::NO,
            self::CHECKOUT,
            null
        );

        $this->assertSame(self::CHECKOUT, $hint);
    }
}
