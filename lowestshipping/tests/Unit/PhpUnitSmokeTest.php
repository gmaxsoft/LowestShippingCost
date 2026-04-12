<?php
/**
 * Smoke test — PHPUnit environment is wired correctly.
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

use PHPUnit\Framework\TestCase;

/**
 * Podstawowa weryfikacja środowiska PHPUnit po dodaniu struktury testów.
 */
final class PhpUnitSmokeTest extends TestCase
{
    public function testPhpUnitUruchamiaSie(): void
    {
        $this->assertTrue(true);
    }
}
