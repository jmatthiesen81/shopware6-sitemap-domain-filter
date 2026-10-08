<?php

declare(strict_types=1);

namespace Devable\SitemapDomainFilterBundle;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Answers sitemap requests on excluded hosts with a 404. Without this, the core still serves the sitemap index
 * of the sales channel and language there, which lists the sitemap files of the other domains.
 */
readonly class SitemapRequestSubscriber implements EventSubscriberInterface
{
    /**
     * Routes of Shopware\Storefront\Controller\SitemapController, matched by name so the Storefront stays optional
     */
    public const SITEMAP_ROUTES = [ 'frontend.sitemap.xml', 'frontend.sitemap.proxy' ];

    public function __construct(
        private ExcludedHosts $excludedHosts,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // After the RouterListener (priority 32), which sets _route
        return [ KernelEvents::REQUEST => [ 'onRequest', 0 ] ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!\in_array($request->attributes->get('_route'), self::SITEMAP_ROUTES, true)) {
            return;
        }

        if (!$this->excludedHosts->containsHost($request->getHost())) {
            return;
        }

        $event->setResponse(new Response('', Response::HTTP_NOT_FOUND));
    }
}
