<?php

declare(strict_types=1);

namespace MuckiLogPlugin\tests\ChangeLog;

use MuckiLogPlugin\Administration\Controller\ChangeLogSettingsController;
use MuckiLogPlugin\Core\ConfigPath;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Routing\RouterInterface;

final class ChangeLogSettingsControllerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testReturnsActiveFlagAndTrackedEntities(): void
    {
        $config = $this->getContainer()->get(SystemConfigService::class);
        $config->set(ConfigPath::CONFIG_PATH_ACTIVE_CHANGE_LOG->value, true);
        $config->set(ConfigPath::CONFIG_PATH_CHANGE_LOG_ENTITIES->value, ['product', 'order']);

        static::assertSame(['active' => true, 'entities' => ['product', 'order']], $this->fetchSettings());
    }

    public function testReturnsDefaultsWhenUnset(): void
    {
        $config = $this->getContainer()->get(SystemConfigService::class);
        $config->delete(ConfigPath::CONFIG_PATH_ACTIVE_CHANGE_LOG->value);
        $config->delete(ConfigPath::CONFIG_PATH_CHANGE_LOG_ENTITIES->value);

        static::assertSame(
            ['active' => false, 'entities' => ['product', 'category', 'order', 'customer', 'cms_page']],
            $this->fetchSettings()
        );
    }

    public function testRouteRequiresChangeLogReadPrivilege(): void
    {
        $router = $this->getContainer()->get('router');
        static::assertInstanceOf(RouterInterface::class, $router);

        $route = $router->getRouteCollection()->get('api.action.muwa_change_log.settings');

        static::assertNotNull($route);
        static::assertSame('/api/_action/muwa-change-log/settings', $route->getPath());
        static::assertSame(['GET'], $route->getMethods());
        static::assertSame(['api'], $route->getDefault('_routeScope'));
        static::assertSame(['muwa_admin_change_log:read'], $route->getDefault(PlatformRequest::ATTRIBUTE_ACL));
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchSettings(): array
    {
        $controller = $this->getContainer()->get(ChangeLogSettingsController::class);
        $content = (string) $controller->settings()->getContent();

        return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
    }
}
