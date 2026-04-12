<?php
/**
 * Integration tests against a real PrestaShop (PRESTASHOP_ROOT + config.inc.php).
 *
 * @author    Maxsoft
 * @copyright 2007-2026 Maxsoft
 * @license   https://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

namespace Tests\Integration;

use Configuration;
use Context;
use Cookie;
use Currency;
use Customer;
use Db;
use Language;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use PrestaShop\Module\Lowestshipping\Shipping\LowestShippingEstimator;
use Product;
use ReflectionClass;
use Shop;
use Tools;
use Validate;

use const _DB_PREFIX_;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Wymaga uruchomienia z PRESTASHOP_ROOT wskazującym na katalog sklepu (config/config.inc.php).
 * Moduł powinien leżeć w {PRESTASHOP_ROOT}/modules/lowestshipping/.
 */
#[Group('integration')]
final class LowestShippingIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Context::class, false)) {
            $this->markTestSkipped(
                'Brak jądra PrestaShop (klasa Context). Ustaw PRESTASHOP_ROOT na katalog sklepu z config/config.inc.php i uruchom PHPUnit ponownie.',
            );
        }
    }

    public function testWersjaPrestaShopJestZdefiniowana(): void
    {
        $this->assertNotEmpty(_PS_VERSION_);
    }

    public function testEstimatorDlaNieistniejacegoProduktuZwracaInvalidProduct(): void
    {
        $ctx = $this->uzupelnijKontekstMinimalny();
        $estimator = new LowestShippingEstimator();
        $wynik = $estimator->estimateDetailed(
            $ctx,
            999_999_999,
            0,
            1,
            (int) Configuration::get('PS_COUNTRY_DEFAULT'),
            true,
        );

        $this->assertFalse($wynik['available']);
        $this->assertSame('invalid_product', $wynik['reason']);
    }

    public function testEstimatorDlaAktywnegoProduktuZwracaWynikBezWyjatku(): void
    {
        $idProduct = $this->pierwszyAktywnyProduktNieWirtualny();
        if ($idProduct < 1) {
            $this->markTestSkipped('Brak aktywnego produktu nie-wirtualnego w bazie sklepu.');
        }

        $ctx = $this->uzupelnijKontekstMinimalny();
        $idAttr = (int) Product::getDefaultAttribute($idProduct);

        $estimator = new LowestShippingEstimator();
        $wynik = $estimator->estimateDetailed(
            $ctx,
            $idProduct,
            $idAttr,
            1,
            (int) Configuration::get('PS_COUNTRY_DEFAULT'),
            true,
        );

        $this->assertArrayHasKey('available', $wynik);
        $this->assertArrayHasKey('reason', $wynik);
    }

    public function testScenariuszDarmowejDostawy(): void
    {
        if (getenv('LOWESTSHIPPING_INTEGRATION_FREE_SHIPPING') !== '1') {
            $this->markTestSkipped('Ustaw LOWESTSHIPPING_INTEGRATION_FREE_SHIPPING=1 w środowisku z regułą darmowej dostawy dla testowego koszyka.');
        }

        $idProduct = $this->pierwszyAktywnyProduktNieWirtualny();
        if ($idProduct < 1) {
            $this->markTestSkipped('Brak produktu do scenariusza.');
        }

        $ctx = $this->uzupelnijKontekstMinimalny();
        $idAttr = (int) Product::getDefaultAttribute($idProduct);
        $estimator = new LowestShippingEstimator();
        $wynik = $estimator->estimateDetailed(
            $ctx,
            $idProduct,
            $idAttr,
            1,
            (int) Configuration::get('PS_COUNTRY_DEFAULT'),
            true,
        );

        $this->assertTrue($wynik['available']);
        $this->assertTrue($wynik['is_free_shipping']);
        $this->assertSame(0.0, (float) $wynik['price']);
    }

    public function testScenariuszBrakDostawyZaCiezki(): void
    {
        if (getenv('LOWESTSHIPPING_INTEGRATION_TOO_HEAVY') !== '1') {
            $this->markTestSkipped('Ustaw LOWESTSHIPPING_INTEGRATION_TOO_HEAVY=1 oraz LOWESTSHIPPING_TEST_PRODUCT_ID na produkt przekraczający limity wszystkich przewoźników.');
        }

        $idProduct = (int) getenv('LOWESTSHIPPING_TEST_PRODUCT_ID');
        if ($idProduct < 1) {
            $this->markTestSkipped('Brak LOWESTSHIPPING_TEST_PRODUCT_ID.');
        }

        $ctx = $this->uzupelnijKontekstMinimalny();
        $idAttr = (int) Product::getDefaultAttribute($idProduct);
        $estimator = new LowestShippingEstimator();
        $wynik = $estimator->estimateDetailed(
            $ctx,
            $idProduct,
            $idAttr,
            999,
            (int) Configuration::get('PS_COUNTRY_DEFAULT'),
            true,
        );

        $this->assertFalse($wynik['available']);
        $this->assertSame('no_carriers', $wynik['reason']);
    }

    public function testScenariuszBrakPrzewoznikowKrajBezStrefy(): void
    {
        if (getenv('LOWESTSHIPPING_INTEGRATION_NO_CARRIERS') !== '1') {
            $this->markTestSkipped('Ustaw LOWESTSHIPPING_INTEGRATION_NO_CARRIERS=1 oraz LOWESTSHIPPING_TEST_COUNTRY_ID na kraj bez przypisanych przewoźników.');
        }

        $countryId = (int) getenv('LOWESTSHIPPING_TEST_COUNTRY_ID');
        if ($countryId < 1) {
            $this->markTestSkipped('Brak LOWESTSHIPPING_TEST_COUNTRY_ID.');
        }

        $idProduct = $this->pierwszyAktywnyProduktNieWirtualny();
        if ($idProduct < 1) {
            $this->markTestSkipped('Brak produktu.');
        }

        $ctx = $this->uzupelnijKontekstMinimalny();
        $idAttr = (int) Product::getDefaultAttribute($idProduct);
        $estimator = new LowestShippingEstimator();
        $wynik = $estimator->estimateDetailed(
            $ctx,
            $idProduct,
            $idAttr,
            1,
            $countryId,
            true,
        );

        $this->assertFalse($wynik['available']);
        $this->assertSame('no_carriers', $wynik['reason']);
    }

    public function testDynamicznaZmianaKombinacjiPrzezZapytanie(): void
    {
        $idProduct = $this->pierwszyProduktZDwomaKombinacjami();
        if ($idProduct < 1) {
            $this->markTestSkipped('Brak produktu z co najmniej dwiema kombinacjami.');
        }

        $ids = Product::getProductAttributesIds($idProduct, true);
        if (count($ids) < 2) {
            $this->markTestSkipped('Za mało kombinacji w produkcie.');
        }

        $ctx = $this->uzupelnijKontekstMinimalny();
        $estimator = new LowestShippingEstimator();
        $countryId = (int) Configuration::get('PS_COUNTRY_DEFAULT');

        $prev = null;
        foreach (array_slice($ids, 0, 2) as $row) {
            $idAttr = (int) $row['id_product_attribute'];
            $_GET['id_product_attribute'] = $idAttr;
            $fromTools = (int) Tools::getValue('id_product_attribute');
            $this->assertSame($idAttr, $fromTools);

            $wynik = $estimator->estimateDetailed($ctx, $idProduct, $idAttr, 1, $countryId, true);
            $this->assertArrayHasKey('available', $wynik);
            $prev = $wynik;
        }

        $this->assertNotNull($prev);
        unset($_GET['id_product_attribute']);
    }

    private function uzupelnijKontekstMinimalny(): Context
    {
        $ref = new ReflectionClass('Context');
        /** @var Context $ctx */
        $ctx = $ref->getMethod('getContext')->invoke(null);
        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $idShop = (int) Configuration::get('PS_SHOP_DEFAULT');

        if (!Validate::isLoadedObject($ctx->language)) {
            $ctx->language = new Language($idLang);
        }
        if (!Validate::isLoadedObject($ctx->shop)) {
            $ctx->shop = new Shop($idShop);
        }
        if (!Validate::isLoadedObject($ctx->currency)) {
            $def = Currency::getDefaultCurrency();
            $ctx->currency = $def instanceof Currency ? $def : new Currency((int) Configuration::get('PS_CURRENCY_DEFAULT'));
        }
        if (!Validate::isLoadedObject($ctx->customer)) {
            $c = new Customer();
            $c->id = 0;
            $ctx->customer = $c;
        }
        if ($ctx->cookie === null) {
            $ctx->cookie = new Cookie('ps');
        }

        return $ctx;
    }

    private function pierwszyAktywnyProduktNieWirtualny(): int
    {
        $sql = 'SELECT id_product FROM ' . _DB_PREFIX_ . 'product WHERE active = 1 AND is_virtual = 0 ORDER BY id_product ASC';

        return (int) Db::getInstance()->getValue($sql);
    }

    private function pierwszyProduktZDwomaKombinacjami(): int
    {
        $sql = 'SELECT p.id_product FROM ' . _DB_PREFIX_ . 'product p INNER JOIN ' . _DB_PREFIX_ . 'product_attribute pa ON pa.id_product = p.id_product WHERE p.active = 1 AND p.is_virtual = 0 GROUP BY p.id_product HAVING COUNT(pa.id_product_attribute) >= 2 ORDER BY p.id_product ASC';

        return (int) Db::getInstance()->getValue($sql);
    }
}
