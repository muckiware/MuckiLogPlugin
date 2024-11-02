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

    public function testCheckValidEmailFunction(): void
    {
        $helperClass = new Helper();
        $isValidEmailResults = $helperClass->isValidEmail('test@test.com');
        static::assertIsBool($isValidEmailResults, 'isValidEmailResult is boolean');
        static::assertTrue($isValidEmailResults, 'isValidEmailResult should be true. E-Mail is valid');

        $isValidEmailResultsNoValid = $helperClass->isValidEmail('test_test.com');
        static::assertFalse($isValidEmailResultsNoValid, 'isValidEmailResult should be false. E-Mail not valid');
    }
}
