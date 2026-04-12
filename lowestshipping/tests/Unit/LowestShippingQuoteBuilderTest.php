<?php
/**
 * Unit tests for LowestShippingQuoteBuilder.
 *
 * @author    Maxsoft
 * @copyright 2007-2026 Maxsoft
 * @license   https://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

namespace Tests\Unit;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Lowestshipping\Shipping\LowestShippingQuoteBuilder;

final class LowestShippingQuoteBuilderTest extends TestCase
{
    #[DataProvider('providerPrefiksyIOpcjeWierszaDostepnego')]
    public function testBuildAvailableRowMapujePrefiksFormatIWierszPrzewoznika(
        string $prefix,
        string $formattedHtml,
        string $carrierName,
        bool $isFree,
        string $carrierLine,
    ): void {
        $row = LowestShippingQuoteBuilder::buildAvailableRow(
            12.5,
            $prefix . $formattedHtml,
            $carrierName,
            $isFree,
            $carrierLine,
        );

        $this->assertTrue($row['available']);
        $this->assertSame(12.5, $row['price']);
        $this->assertSame($prefix . $formattedHtml, $row['formatted_price']);
        $this->assertSame($carrierName, $row['carrier_name']);
        $this->assertSame($isFree, $row['is_free_shipping']);
        $this->assertSame($carrierLine, $row['carrier_line']);
        $this->assertSame('', $row['hint_message']);
    }

    public static function providerPrefiksyIOpcjeWierszaDostepnego(): array
    {
        return [
            'bez_prefiksu' => ['', '<span>12,50</span>', 'DHL', false, 'Przewoźnik: DHL'],
            'z_prefiksem_html' => ['<b>Od </b>', '<span>10</span>', 'Poczta', false, 'Carrier: Poczta'],
            'darmowa' => ['', '0,00 zł', '', true, ''],
        ];
    }

    public function testUnavailableWithHintZwracaNullCeneIKomunikat(): void
    {
        $row = LowestShippingQuoteBuilder::unavailableWithHint('Brak przewoźników');

        $this->assertFalse($row['available']);
        $this->assertNull($row['price']);
        $this->assertSame('', $row['formatted_price']);
        $this->assertSame('', $row['carrier_name']);
        $this->assertFalse($row['is_free_shipping']);
        $this->assertSame('', $row['carrier_line']);
        $this->assertSame('Brak przewoźników', $row['hint_message']);
    }
}
