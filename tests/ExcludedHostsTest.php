<?php

declare(strict_types=1);

namespace Devable\SitemapDomainFilterBundle\Tests;

use Devable\SitemapDomainFilterBundle\ExcludedHosts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExcludedHostsTest extends TestCase
{
    /**
     * @return iterable<string, array{string|null, bool}>
     */
    public static function urlProvider(): iterable
    {
        yield 'https' => [ 'https://devable.me', true ];
        yield 'with path' => [ 'https://devable.me/en', true ];
        yield 'with port' => [ 'https://devable.me:8443', true ];
        yield 'upper case' => [ 'https://DevAble.ME', true ];
        yield 'other host' => [ 'https://netinventors.de', false ];
        yield 'www variant' => [ 'https://www.devable.me', false ];
        yield 'subdomain suffix' => [ 'https://devable.me.example.com', false ];
        yield 'no host' => [ '/relative/path', false ];
        yield 'null' => [ null, false ];
    }

    #[DataProvider('urlProvider')]
    public function testContainsUrl(string|null $url, bool $expected): void
    {
        static::assertSame($expected, (new ExcludedHosts([ 'devable.me' ]))->containsUrl($url));
    }

    public function testConfiguredHostsAreMatchedCaseInsensitive(): void
    {
        $excludedHosts = new ExcludedHosts([ 'Devable.me' ]);

        static::assertTrue($excludedHosts->containsHost('DEVABLE.ME'));
        static::assertFalse($excludedHosts->containsHost('www.devable.me'));
    }
}
