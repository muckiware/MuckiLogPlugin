<?php

declare(strict_types=1);

namespace MuckiLogPlugin\Services;

use PHPUnit\Framework\TestCase;

use MuckiLogPlugin\Services\Helper;

class HelperTest extends TestCase
{
    public function testCheckHelperFunction(): void
    {
        $helperClass = new Helper();
        $hashData = $helperClass->getHashData('abc123');
        static::assertIsString($hashData, 'hash data method with string result as md5 hash');

        $hashData = $helperClass->getHashData(['abc123']);
        static::assertIsString($hashData, 'hash data method with string result as md5 hash');
    }
}
