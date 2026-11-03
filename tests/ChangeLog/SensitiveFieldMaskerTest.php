<?php

declare(strict_types=1);

namespace MuckiLogPlugin\tests\ChangeLog;

use MuckiLogPlugin\ChangeLog\SensitiveFieldMasker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;

class SensitiveFieldMaskerTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function sensitiveProvider(): iterable
    {
        foreach (['password', 'newPassword', 'legacy_password', 'secretAccessKey', 'secret_access_key', 'apiKey', 'accessKey', 'privateKey', 'token', 'refreshToken', 'hash', 'passwordHash', 'portalKey', 'hmacKey', 'licenseKey', 'webhookKey', 'encryptionKey', 'signingKey', 'merchantKey', 'credentials', 'passphrase', 'hmac_secret', 'key', 'sortKey', 'api_credential'] as $name) {
            yield $name => [$name];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function harmlessProvider(): iterable
    {
        foreach (['name', 'description', 'stock', 'productNumber', 'customerNumber', 'keywords', 'keyName'] as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('sensitiveProvider')]
    public function testSensitive(string $name): void
    {
        static::assertTrue((new SensitiveFieldMasker())->isSensitive($name));
    }

    #[DataProvider('harmlessProvider')]
    public function testHarmless(string $name): void
    {
        static::assertFalse((new SensitiveFieldMasker())->isSensitive($name));
    }

    public function testMaskValueMasksSensitiveKeysAtAnyDepth(): void
    {
        $masked = false;
        $value = ['user' => 'a', 'nested' => ['credentials' => ['user' => 'a', 'password' => 'b'], 'list' => [['portalKey' => 'p', 'x' => 1]]]];

        $result = (new SensitiveFieldMasker())->maskValue($value, $masked);

        static::assertTrue($masked);
        static::assertSame(['user' => 'a', 'nested' => ['credentials' => '***', 'list' => [['portalKey' => '***', 'x' => 1]]]], $result);
    }

    public function testMaskValueLeavesHarmlessValuesAndFlag(): void
    {
        $masked = false;

        static::assertSame(['a' => ['b' => 1]], (new SensitiveFieldMasker())->maskValue(['a' => ['b' => 1]], $masked));
        static::assertSame('plain', (new SensitiveFieldMasker())->maskValue('plain', $masked));
        static::assertFalse($masked);
    }

    public function testPasswordFieldIsAlwaysSensitive(): void
    {
        $field = new PasswordField('foo', 'foo');
        static::assertTrue((new SensitiveFieldMasker())->isSensitive('foo', $field));
    }
}
