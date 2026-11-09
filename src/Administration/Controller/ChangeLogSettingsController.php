<?php

declare(strict_types=1);

namespace MuckiLogPlugin\Administration\Controller;

use MuckiLogPlugin\ChangeLog\ChangeLogConfig;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Exposes the change log switches to admin users with the change log viewer
 * role, who must not need system_config:read to see the detail tabs.
 */
#[Route(defaults: ['_routeScope' => ['api']])]
final class ChangeLogSettingsController
{
    public function __construct(
        private readonly ChangeLogConfig $config
    ) {
    }

    #[Route(
        path: '/api/_action/muwa-change-log/settings',
        name: 'api.action.muwa_change_log.settings',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['muwa_admin_change_log:read']],
        methods: ['GET']
    )]
    public function settings(): JsonResponse
    {
        return new JsonResponse([
            'active' => $this->config->isActive(),
            'entities' => $this->config->getTrackedEntities(),
        ]);
    }
}
