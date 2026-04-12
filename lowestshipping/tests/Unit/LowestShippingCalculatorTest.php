<?php
/**
 * Unit tests for LowestShippingCalculator.
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

use Carrier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Lowestshipping\Shipping\LowestShippingCalculator;

final class LowestShippingCalculatorTest extends TestCase
{
    public function testSelectBestDeliveryOptionBrakPrzewoznikowZwracaNull(): void
    {
        $this->assertNull(LowestShippingCalculator::selectBestDeliveryOption([], true, 1));
    }

    public function testSelectBestDeliveryOptionWybieraNajnizszaCeneZPodatkiem(): void
    {
        $list = $this->wrapLayer([
            $this->option(20.0, 16.0, false, []),
            $this->option(10.0, 8.0, false, []),
        ]);

        $best = LowestShippingCalculator::selectBestDeliveryOption($list, true, 1);

        $this->assertNotNull($best);
        $this->assertSame(10.0, $best['price']);
        $this->assertFalse($best['is_free_shipping']);
    }

    public function testSelectBestDeliveryOptionWybieraNajnizszaCeneBezPodatku(): void
    {
        $list = $this->wrapLayer([
            $this->option(20.0, 5.0, false, []),
            $this->option(30.0, 4.0, false, []),
        ]);

        $best = LowestShippingCalculator::selectBestDeliveryOption($list, false, 1);

        $this->assertNotNull($best);
        $this->assertSame(4.0, $best['price']);
    }

    public function testSelectBestDeliveryOptionDarmowaDostawaFlaga(): void
    {
        $carrier = $this->carrierNamed('FreeCo');
        $list = $this->wrapLayer([
            $this->option(0.0, 0.0, true, [$carrier]),
        ]);

        $best = LowestShippingCalculator::selectBestDeliveryOption($list, true, 1);

        $this->assertNotNull($best);
        $this->assertTrue($best['is_free_shipping']);
        $this->assertSame(0.0, $best['price']);
        $this->assertSame('FreeCo', $best['carrier_name']);
    }

    /**
     * „Za ciężki” w praktyce daje pustą listę opcji lub brak poprawnych wpisów — jak pusty wynik.
     */
    public function testSelectBestDeliveryOptionBrakPoprawnychOpcjiZwracaNull(): void
    {
        $list = [
            1 => [
                'broken' => ['foo' => 'bar'],
            ],
        ];

        $this->assertNull(LowestShippingCalculator::selectBestDeliveryOption($list, true, 1));
    }

    /**
     * Cena jak przy produkcie bez wagi (0) — już policzona w totalach przewoźnika.
     */
    #[DataProvider('providerCenyJakPrzyWadzeZero')]
    public function testSelectBestDeliveryOptionAkceptujeNiskieCenyJakPrzyLekkimProdukcie(
        float $withTax,
        float $withoutTax,
        bool $useTax,
        float $expected,
    ): void {
        $list = $this->wrapLayer([
            $this->option($withTax, $withoutTax, false, []),
        ]);

        $best = LowestShippingCalculator::selectBestDeliveryOption($list, $useTax, 1);

        $this->assertNotNull($best);
        $this->assertSame($expected, $best['price']);
    }

    public static function providerCenyJakPrzyWadzeZero(): array
    {
        return [
            'minimalnaZPodatkiem' => [1.99, 1.62, true, 1.99],
            'minimalnaBezPodatku' => [1.99, 1.62, false, 1.62],
        ];
    }

    /**
     * Kombinacja = inna cena w paczce — symulowane gotowymi totalami (additional_shipping + tax w kwocie).
     */
    #[DataProvider('providerKombinacjaInnaCena')]
    public function testSelectBestDeliveryOptionRozneTotalyJakPrzyKombinacji(
        array $options,
        bool $withTax,
        float $expectedPrice,
    ): void {
        $list = $this->deliveryListWithOptions($options);
        $best = LowestShippingCalculator::selectBestDeliveryOption($list, $withTax, 1);

        $this->assertNotNull($best);
        $this->assertSame($expectedPrice, $best['price']);
    }

    public static function providerKombinacjaInnaCena(): array
    {
        return [
            'wariant_ciezszy' => [
                [
                    ['wt' => 25.0, 'wot' => 20.0, 'free' => false, 'carriers' => []],
                    ['wt' => 40.0, 'wot' => 32.0, 'free' => false, 'carriers' => []],
                ],
                true,
                25.0,
            ],
        ];
    }

    public function testResolveCarrierLabelWielojezycznaTablica(): void
    {
        $carrier = $this->carrierNamed(['2' => 'PL', '1' => 'EN']);
        $this->assertSame('PL', LowestShippingCalculator::resolveCarrierLabel($carrier, 2));
    }

    public function testResolveCarrierLabelFallbackPierwszyJezyk(): void
    {
        $carrier = $this->carrierNamed(['1' => 'Only', '5' => '']);
        $this->assertSame('Only', LowestShippingCalculator::resolveCarrierLabel($carrier, 99));
    }

    /**
     * Kwota z additional_shipping_cost i podatkiem już „wtopiona” w total_price_* (jak w Cart::getDeliveryOptionList).
     */
    #[DataProvider('providerAdditionalShippingZPodatkiem')]
    public function testSelectBestDeliveryOptionUwzgledniaTotalyZAdditionalShipping(
        float $withTax,
        float $withoutTax,
        bool $useTax,
        float $expected,
    ): void {
        $list = $this->wrapLayer([
            $this->option($withTax, $withoutTax, false, []),
        ]);

        $best = LowestShippingCalculator::selectBestDeliveryOption($list, $useTax, 1);

        $this->assertNotNull($best);
        $this->assertSame($expected, $best['price']);
    }

    public static function providerAdditionalShippingZPodatkiem(): array
    {
        return [
            'z_podatkiem' => [15.49, 12.60, true, 15.49],
            'bez_podatku' => [15.49, 12.60, false, 12.60],
        ];
    }

    public function testSelectBestDeliveryOptionPustaListaPrzewoznikowWNajlepszejOpcjiPustaNazwa(): void
    {
        $list = $this->wrapLayer([
            [
                'total_price_with_tax' => 7.0,
                'total_price_without_tax' => 5.7,
                'is_free' => false,
                'carrier_list' => [],
            ],
        ]);

        $best = LowestShippingCalculator::selectBestDeliveryOption($list, true, 1);

        $this->assertNotNull($best);
        $this->assertSame('', $best['carrier_name']);
        $this->assertSame(7.0, $best['price']);
    }

    public function testSelectBestDeliveryOptionLaczyWielePrzewoznikowWNazwie(): void
    {
        $a = $this->carrierNamed('A');
        $b = $this->carrierNamed('B');
        $list = $this->wrapLayer([
            $this->option(5.0, 4.0, false, [$a, $b]),
        ]);

        $best = LowestShippingCalculator::selectBestDeliveryOption($list, true, 1);

        $this->assertNotNull($best);
        $this->assertSame('A + B', $best['carrier_name']);
    }

    /**
     * @param list<array{total_price_with_tax: float, total_price_without_tax: float, is_free: bool, carrier_list: array<int, array<string, mixed>}> $options
     */
    private function wrapLayer(array $options): array
    {
        return [1 => $options];
    }

    /**
     * @param array<int, array{wt: float, wot: float, free: bool, carriers: array<int, Carrier>}> $rows
     */
    private function deliveryListWithOptions(array $rows): array
    {
        $opts = [];
        foreach ($rows as $r) {
            $carrierList = [];
            foreach ($r['carriers'] as $i => $c) {
                $carrierList[$i + 1] = [
                    'instance' => $c,
                    'price_with_tax' => $r['wt'],
                    'price_without_tax' => $r['wot'],
                ];
            }
            $opts[] = [
                'total_price_with_tax' => $r['wt'],
                'total_price_without_tax' => $r['wot'],
                'is_free' => $r['free'],
                'carrier_list' => $carrierList,
            ];
        }

        return [1 => $opts];
    }

    private function option(float $wt, float $wot, bool $free, array $carriers): array
    {
        $carrierList = [];
        foreach ($carriers as $i => $c) {
            $carrierList[$i + 1] = [
                'instance' => $c,
                'price_with_tax' => $wt,
                'price_without_tax' => $wot,
            ];
        }

        return [
            'total_price_with_tax' => $wt,
            'total_price_without_tax' => $wot,
            'is_free' => $free,
            'carrier_list' => $carrierList,
        ];
    }

    private function carrierNamed(string|array $name): Carrier
    {
        $mock = $this->getMockBuilder(Carrier::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $mock->name = $name;

        return $mock;
    }
}
