<?php

declare(strict_types=1);

namespace Devable\SitemapDomainFilterBundle\Tests;

use Devable\SitemapDomainFilterBundle\ExcludedHosts;
use Devable\SitemapDomainFilterBundle\SitemapRequestSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class SitemapRequestSubscriberTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function blockedRequestProvider(): iterable
    {
        yield 'index' => [ 'https://devable.me/sitemap.xml', 'frontend.sitemap.xml' ];
        yield 'file proxy' => [ 'https://devable.me/sitemap/salesChannel-sc-lang/file.xml.gz', 'frontend.sitemap.proxy' ];
        yield 'upper case host' => [ 'https://DevAble.ME/sitemap.xml', 'frontend.sitemap.xml' ];
        yield 'with port and path' => [ 'https://devable.me:8443/en/sitemap.xml', 'frontend.sitemap.xml' ];
    }

    #[DataProvider('blockedRequestProvider')]
    public function testSitemapOnExcludedHostIsNotFound(string $uri, string $route): void
    {
        $event = $this->dispatch($uri, $route);

        static::assertSame(Response::HTTP_NOT_FOUND, $event->getResponse()?->getStatusCode());
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function passedRequestProvider(): iterable
    {
        yield 'other host' => [ 'https://netinventors.de/sitemap.xml', 'frontend.sitemap.xml' ];
        yield 'www variant' => [ 'https://www.devable.me/sitemap.xml', 'frontend.sitemap.xml' ];
        yield 'other route' => [ 'https://devable.me/', 'frontend.home.page' ];
        yield 'robots.txt' => [ 'https://devable.me/robots.txt', 'frontend.robots.txt' ];
        yield 'no route' => [ 'https://devable.me/sitemap.xml', null ];
    }

    #[DataProvider('passedRequestProvider')]
    public function testOtherRequestsPass(string $uri, string|null $route): void
    {
        static::assertNull($this->dispatch($uri, $route)->getResponse());
    }

    public function testSubRequestPasses(): void
    {
        $event = $this->dispatch('https://devable.me/sitemap.xml', 'frontend.sitemap.xml', HttpKernelInterface::SUB_REQUEST);

        static::assertNull($event->getResponse());
    }

    public function testRunsAfterRouterListener(): void
    {
        $priority = SitemapRequestSubscriber::getSubscribedEvents()['kernel.request'][1];

        static::assertLessThan(32, $priority);
    }

    private function dispatch(string $uri, string|null $route, int $requestType = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $request = Request::create($uri);
        if (null !== $route) {
            $request->attributes->set('_route', $route);
        }

        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, $requestType);

        (new SitemapRequestSubscriber(new ExcludedHosts([ 'devable.me' ])))->onRequest($event);

        return $event;
    }
}
