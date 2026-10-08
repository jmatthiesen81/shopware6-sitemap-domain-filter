<?php

declare(strict_types=1);

namespace Devable\SitemapDomainFilterBundle\Tests;

use Devable\SitemapDomainFilterBundle\ExcludedHosts;
use Devable\SitemapDomainFilterBundle\RobotsSitemapSubscriber;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Storefront\Page\Robots\RobotsPage;
use Shopware\Storefront\Page\Robots\RobotsPageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class RobotsSitemapSubscriberTest extends TestCase
{
    public function testSitemapsOfExcludedHostsAreRemoved(): void
    {
        $page = new RobotsPage();
        $page->setSitemaps([
            'https://devable.me/sitemap.xml',
            'https://www.devable.me/sitemap.xml',
            'http://DevAble.me/en/sitemap.xml',
            'https://netinventors.de/sitemap.xml',
        ]);

        $subscriber = new RobotsSitemapSubscriber(new ExcludedHosts([ 'devable.me' ]));
        $subscriber->onRobotsPageLoaded(new RobotsPageLoadedEvent($page, Context::createDefaultContext(), new Request()));

        static::assertSame(
            [ 'https://www.devable.me/sitemap.xml', 'https://netinventors.de/sitemap.xml' ],
            $page->getSitemaps(),
        );
    }

    public function testSubscribesToRobotsPageLoadedEvent(): void
    {
        static::assertArrayHasKey(RobotsPageLoadedEvent::class, RobotsSitemapSubscriber::getSubscribedEvents());
    }
}
