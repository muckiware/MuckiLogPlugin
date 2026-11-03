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
        foreach (['password', 'newPassword', 'legacy_password', 'secretAccessKey', 'secret_access_key', 'apiKey', 'accessKey', 'privateKey', 'token', 'refreshToken', 'hash', 'passwordHash'] as $name) {
            yield $name => [$name];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function harmlessProvider(): iterable
    {
        foreach (['name', 'description', 'stock'] as $name) {
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

    public function testPasswordFieldIsAlwaysSensitive(): void
    {
        $field = new PasswordField('foo', 'foo');
        static::assertTrue((new SensitiveFieldMasker())->isSensitive('foo', $field));
    }
}
