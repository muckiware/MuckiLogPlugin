<?php

declare(strict_types=1);

namespace MuckiLogPlugin\Administration\Controller;

use MuckiLogPlugin\Services\LogViewerInterface;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
final class LogViewerController
{
    public function __construct(
        private readonly LogViewerInterface $logViewer
    ) {
    }

    #[Route(
        path: '/api/_action/muwa-log-viewer/files',
        name: 'api.action.muwa_log_viewer.files',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['muwa_log_viewer:read']],
        methods: ['GET']
    )]
    public function files(): JsonResponse
    {
        return new JsonResponse(['files' => $this->logViewer->listFiles()]);
    }

    #[Route(
        path: '/api/_action/muwa-log-viewer/content',
        name: 'api.action.muwa_log_viewer.content',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['muwa_log_viewer:read']],
        methods: ['GET']
    )]
    public function content(Request $request): JsonResponse
    {
        $file = (string) $request->query->get('file', '');
        if ($file === '') {
            return new JsonResponse(
                ['errors' => [['detail' => 'Missing "file" parameter.']]],
                Response::HTTP_BAD_REQUEST
            );
        }

        $lines = max(1, min((int) $request->query->get('lines', 2000), 20000));
        $beforeByte = $request->query->has('before') ? (int) $request->query->get('before') : null;

        try {
            $chunk = $this->logViewer->readTail($file, $lines, $beforeByte);
        } catch (\RuntimeException $exception) {
            return new JsonResponse(
                ['errors' => [['detail' => $exception->getMessage()]]],
                Response::HTTP_NOT_FOUND
            );
        }

        return new JsonResponse($chunk);
    }

    #[Route(
        path: '/api/_action/muwa-log-viewer/download',
        name: 'api.action.muwa_log_viewer.download',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['muwa_log_viewer:read']],
        methods: ['GET']
    )]
    public function download(Request $request): Response
    {
        $file = (string) $request->query->get('file', '');

        try {
            $path = $this->logViewer->getRealPath($file);
        } catch (\RuntimeException $exception) {
            return new JsonResponse(
                ['errors' => [['detail' => $exception->getMessage()]]],
                Response::HTTP_NOT_FOUND
            );
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, basename($path));

        return $response;
    }
}
