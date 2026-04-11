<?php

declare(strict_types=1);

namespace Tests\Unit;

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
