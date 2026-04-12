<?php
/**
 * PHPUnit bootstrap — Composer autoload and optional PrestaShop (PRESTASHOP_ROOT).
 *
 * @author    Maxsoft
 * @copyright 2007-2026 Maxsoft
 * @license   https://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Uruchom: composer install (brak vendor/autoload.php).\n");
    exit(1);
}

require_once $autoload;

$psRoot = getenv('PRESTASHOP_ROOT') ?: '';
$psConfig = $psRoot !== '' ? $psRoot . '/config/config.inc.php' : '';
if ($psConfig !== '' && is_file($psConfig)) {
    require_once $psConfig;
} elseif (!class_exists('Carrier', false)) {
    /**
     * Minimalna klasa Carrier poza PrestaShop — tylko testy jednostkowe kalkulatora.
     */
    class Carrier
    {
        public string|array $name = '';
    }
}
