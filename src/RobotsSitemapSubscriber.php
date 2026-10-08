<?php

declare(strict_types=1);

namespace Devable\SitemapDomainFilterBundle;

use Shopware\Storefront\Page\Robots\RobotsPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Removes the "Sitemap:" lines of excluded hosts from the robots.txt, as their sitemap.xml answers with a 404.
 * Only registered when the Storefront provides the robots.txt (Shopware 6.7.1+).
 */
readonly class RobotsSitemapSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ExcludedHosts $excludedHosts,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [ RobotsPageLoadedEvent::class => 'onRobotsPageLoaded' ];
    }

    public function onRobotsPageLoaded(RobotsPageLoadedEvent $event): void
    {
        $page = $event->getPage();

        $page->setSitemaps(\array_values(\array_filter(
            $page->getSitemaps(),
            fn (string $url): bool => !$this->excludedHosts->containsUrl($url),
        )));
    }
}
