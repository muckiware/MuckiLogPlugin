<?php declare(strict_types=1);

namespace MuckiLogPlugin\Storefront\Controller;


use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Twig\ErrorTemplateResolver;
use Shopware\Storefront\Page\Navigation\Error\ErrorPageLoaderInterface;
use Shopware\Storefront\Pagelet\Footer\FooterPageletLoaderInterface;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoaderInterface;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Controller\ErrorController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
    protected ErrorController $originalErrorController;
    /**
     * @internal
     */
    public function __construct(
        ErrorController $errorController,
        protected LoggerInterface $logger,
        private readonly ErrorTemplateResolver $errorTemplateResolver,
        private readonly HeaderPageletLoaderInterface $headerPageletLoader,
        private readonly SystemConfigService $systemConfigService,
        private readonly ErrorPageLoaderInterface $errorPageLoader,
        private readonly FooterPageletLoaderInterface $footerPageletLoader
    )
    {
        $this->originalLoggerService = $errorController;
    }

    public function error(\Throwable $exception, Request $request, SalesChannelContext $context): Response
    {
        $session = $request->hasSession() ? $request->getSession() : null;

        try {
            $is404StatusCode = $exception instanceof HttpException
                && $exception->getStatusCode() === Response::HTTP_NOT_FOUND;

            if (!$is404StatusCode && $session !== null && $session instanceof FlashBagAwareSessionInterface && !$session->getFlashBag()->has('danger')) {

                $errorID = \Shopware\Core\Framework\Uuid\Uuid::randomHex();
                $requestUrl = $request->attributes->get('sw-sales-channel-absolute-base-url').$request->attributes->get('sw-original-request-uri');
                $languageID = $request->headers->get('sw-language-id');

                $this->logger->error('Message ID: '.$errorID, ['storefront', 'exceptions']);
                $this->logger->error('Request: '.$requestUrl, ['storefront', 'exceptions']);
                $this->logger->error('languageID: '.$languageID, ['storefront', 'exceptions']);
                $this->logger->error('ErrorController exception: '.print_r($exception, true), ['storefront', 'exceptions']);
                $session->getFlashBag()->add(
                    'danger',
                    sprintf('%s<br>ID: <b>%s</b>', $this->trans('error.message-default'), $errorID)
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

                // cache_rework is active by default in SW 6.7 — header/footer loaded via ESI

                $response = $this->renderStorefront($errorTemplate->getTemplateName(), ['page' => $errorTemplate]);
            }

            if ($exception instanceof HttpException) {
                $response->setStatusCode($exception->getStatusCode());
            }
        } catch (\Exception $e) { // final Fallback
            $response = $this->renderStorefront(
                '@Storefront/storefront/page/error/index.html.twig',
                ['exception' => $exception, 'followingException' => $e]
            );

            $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // After this controllers content is rendered (even if the flashbag was not used e.g. on a 404 page),
        // clear the existing flashbag messages

        if ($session !== null && $session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->clear();
        }

        return $response;
    }

    public function onCaptchaFailure(
        ConstraintViolationList $violations,
        Request $request
    ): Response {
        $formViolations = new ConstraintViolationException($violations, []);
        if (!$request->isXmlHttpRequest()) {
            return $this->forwardToRoute($request->get('_route'), ['formViolations' => $formViolations]);
        }

        $response = [];

        // ACCESSIBILITY_TWEAKS is always active in SW 6.7
        $response[] = [
            'type' => 'danger',
            'error' => 'invalid_captcha',
            'alert' => $this->renderView('@Storefront/storefront/utilities/alert.html.twig', [
                'type' => 'danger',
                'list' => [$this->trans('error.' . $formViolations->getViolations()->get(0)->getCode())],
            ]),
        ];

        return new JsonResponse($response);
    }
}
