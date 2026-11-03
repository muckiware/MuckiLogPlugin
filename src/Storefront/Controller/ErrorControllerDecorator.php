<?php declare(strict_types=1);

namespace MuckiLogPlugin\Storefront\Controller;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Twig\ErrorTemplateResolver;
use Shopware\Storefront\Page\Navigation\Error\ErrorPageLoaderInterface;
use Shopware\Storefront\Pagelet\Footer\FooterPageletLoaderInterface;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoaderInterface;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Controller\ErrorController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Package('framework')]
class ErrorControllerDecorator extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ErrorController $originalErrorController,
        protected LoggerInterface $logger,
        private readonly ErrorTemplateResolver $errorTemplateResolver,
        private readonly HeaderPageletLoaderInterface $headerPageletLoader,
        private readonly SystemConfigService $systemConfigService,
        private readonly ErrorPageLoaderInterface $errorPageLoader,
        private readonly FooterPageletLoaderInterface $footerPageletLoader
    )
    {
    }

    public function error(\Throwable $exception, Request $request, SalesChannelContext $context): Response
    {
        $session = $request->hasSession() ? $request->getSession() : null;

        try {
            $is404StatusCode = $exception instanceof HttpException
                && $exception->getStatusCode() === Response::HTTP_NOT_FOUND;

            if (!$is404StatusCode && $session instanceof FlashBagAwareSessionInterface && !$session->getFlashBag()->has('danger')) {
                $errorId = $this->logException($exception, $request);
                $session->getFlashBag()->add(
                    'danger',
                    sprintf('%s<br>ID: <b>%s</b>', $this->trans('error.message-default'), $errorId)
                );
            }

            $request->attributes->set('navigationId', $context->getSalesChannel()->getNavigationCategoryId());

            $salesChannelId = $context->getSalesChannelId();
            $cmsErrorLayoutId = $this->systemConfigService->getString('core.basicInformation.http404Page', $salesChannelId);
            if ($cmsErrorLayoutId !== '' && $is404StatusCode) {
                $errorPage = $this->errorPageLoader->load($cmsErrorLayoutId, $request, $context);

                $response = $this->renderStorefront(
                    '@Storefront/storefront/page/content/index.html.twig',
                    ['page' => $errorPage]
                );
            } else {
                $errorTemplate = $this->errorTemplateResolver->resolve($exception, $request);
                $this->addLegacyHeaderAndFooter($errorTemplate, $request, $context);

                $response = $this->renderStorefront($errorTemplate->getTemplateName(), ['page' => $errorTemplate]);
            }

            if ($exception instanceof HttpException) {
                $response->setStatusCode($exception->getStatusCode());
            }
        } catch (\Throwable $e) { // final Fallback
            $response = $this->renderStorefront(
                '@Storefront/storefront/page/error/index.html.twig',
                ['exception' => $exception, 'followingException' => $e]
            );

            $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // After this controllers content is rendered (even if the flashbag was not used e.g. on a 404 page),
        // clear the existing flashbag messages

        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->clear();
        }

        return $response;
    }

    /**
     * The captcha handling is not extended by this plugin, so the version specific core implementation is used.
     */
    public function onCaptchaFailure(
        ConstraintViolationList $violations,
        Request $request
    ): Response {
        return $this->originalErrorController->onCaptchaFailure($violations, $request);
    }

    private function logException(\Throwable $exception, Request $request): string
    {
        $errorId = Uuid::randomHex();
        $requestUrl = $request->attributes->get('sw-sales-channel-absolute-base-url')
            . $request->attributes->get('sw-original-request-uri');

        $this->logger->error(
            sprintf(
                "Message ID: %s\nRequest: %s\nlanguageID: %s\nErrorController exception: %s: %s in %s:%d\n%s",
                $errorId,
                $requestUrl,
                (string) $request->headers->get('sw-language-id'),
                $exception::class,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getTraceAsString()
            ),
            ['storefront', 'exceptions']
        );

        return $errorId;
    }

    /**
     * Shopware 6.6: ErrorTemplateStruct extends Page and needs header/footer pagelets,
     * unless the cache rework renders them via ESI.
     * Shopware 6.7+: header/footer are always rendered via ESI, setHeader()/setFooter() no longer exist.
     *
     * @deprecated tag:v6.7.0 - Remove once Shopware 6.6 support is dropped
     */
    private function addLegacyHeaderAndFooter(object $errorTemplate, Request $request, SalesChannelContext $context): void
    {
        if ($request->isXmlHttpRequest()) {
            return;
        }

        if (!method_exists($errorTemplate, 'setHeader') || !method_exists($errorTemplate, 'setFooter')) {
            return;
        }

        if (Feature::has('cache_rework') && Feature::isActive('cache_rework')) {
            return;
        }

        $errorTemplate->setHeader($this->headerPageletLoader->load($request, $context));
        $errorTemplate->setFooter($this->footerPageletLoader->load($request, $context));
    }
}
