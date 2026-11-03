<?php

declare(strict_types=1);

namespace MuckiLogPlugin\tests\ChangeLog;

use MuckiLogPlugin\ChangeLog\ChangeLogConfig;
use MuckiLogPlugin\Core\AdminChangeLog\AdminChangeLogDefinition;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class ChangeLogConfigTest extends TestCase
{
    private const DEFAULTS = ['product', 'category', 'order', 'customer', 'cms_page'];

    public function testTrackedEntitiesDefaultWhenUnset(): void
    {
        static::assertSame(self::DEFAULTS, $this->config(null)->getTrackedEntities());
    }

    public function testTrackedEntitiesFiltersNonStrings(): void
    {
        $config = $this->config(['product', 5, '', 'order']);

        static::assertSame(['product', 'order'], $config->getTrackedEntities());
    }

    public function testTrackedEntitiesNeverContainsOwnEntity(): void
    {
        $config = $this->config(['product', AdminChangeLogDefinition::ENTITY_NAME, 'order']);

        static::assertSame(['product', 'order'], $config->getTrackedEntities());
    }

    /**
     * @param mixed $value
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('retentionProvider')]
    public function testRetentionDays(mixed $value, int $expected): void
    {
        $service = $this->createMock(SystemConfigService::class);
        $service->method('get')->willReturn($value);

        static::assertSame($expected, (new ChangeLogConfig($service))->getRetentionDays());
    }

    /**
     * @return iterable<string, array{mixed, int}>
     */
    public static function retentionProvider(): iterable
    {
        yield 'unset' => [null, 90];
        yield 'string' => ['30', 30];
        yield 'negative' => [-5, 0];
        yield 'zero' => [0, 0];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('activeProvider')]
    public function testIsActive(bool $value): void
    {
        $service = $this->createMock(SystemConfigService::class);
        $service->method('getBool')->willReturn($value);

        static::assertSame($value, (new ChangeLogConfig($service))->isActive());
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function activeProvider(): iterable
    {
        yield 'on' => [true];
        yield 'off' => [false];
    }

    private function config(mixed $entities): ChangeLogConfig
    {
        $service = $this->createMock(SystemConfigService::class);
        $service->method('get')->willReturn($entities);

        return new ChangeLogConfig($service);
    }
}
