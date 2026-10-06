<?php

declare(strict_types=1);

namespace Devable\SitemapDomainFilterBundle\Tests;

use Devable\SitemapDomainFilterBundle\DomainFilteringSitemapHandleFactory;
use Devable\SitemapDomainFilterBundle\NullSitemapHandle;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Sitemap\Service\SitemapHandleFactoryInterface;
use Shopware\Core\Content\Sitemap\Service\SitemapHandleInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class DomainFilteringSitemapHandleFactoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function excludedDomainProvider(): iterable
    {
        yield 'https' => [ 'https://devable.me' ];
        yield 'http' => [ 'http://devable.me' ];
        yield 'with path' => [ 'https://devable.me/en' ];
        yield 'with port' => [ 'https://devable.me:8443' ];
        yield 'upper case' => [ 'https://DevAble.ME' ];
    }

    #[DataProvider('excludedDomainProvider')]
    public function testExcludedHostReturnsNullHandle(string $domain): void
    {
        $inner = $this->createMock(SitemapHandleFactoryInterface::class);
        $inner->expects($this->never())->method('create');

        $factory = new DomainFilteringSitemapHandleFactory($inner, [ 'devable.me' ]);

        $handle = $factory->create(
            $this->createStub(FilesystemOperator::class),
            $this->createStub(SalesChannelContext::class),
            $domain,
            'domain-id',
        );

        static::assertInstanceOf(NullSitemapHandle::class, $handle);
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function delegatedDomainProvider(): iterable
    {
        yield 'other host' => [ 'https://netinventors.de' ];
        yield 'www variant' => [ 'https://www.devable.me' ];
        yield 'subdomain suffix' => [ 'https://devable.me.example.com' ];
        yield 'no host' => [ '/relative/path' ];
        yield 'null' => [ null ];
    }

    #[DataProvider('delegatedDomainProvider')]
    public function testOtherDomainsAreDelegatedWithAllArguments(string|null $domain): void
    {
        $filesystem     = $this->createStub(FilesystemOperator::class);
        $context        = $this->createStub(SalesChannelContext::class);
        $expectedHandle = $this->createStub(SitemapHandleInterface::class);

        $inner = $this->createMock(SitemapHandleFactoryInterface::class);
        $inner->expects($this->once())
            ->method('create')
            ->with($filesystem, $context, $domain, 'domain-id')
            ->willReturn($expectedHandle)
        ;

        $factory = new DomainFilteringSitemapHandleFactory($inner, [ 'devable.me' ]);

        static::assertSame($expectedHandle, $factory->create($filesystem, $context, $domain, 'domain-id'));
    }

    public function testConfiguredHostsAreMatchedCaseInsensitive(): void
    {
        $inner = $this->createMock(SitemapHandleFactoryInterface::class);
        $inner->expects($this->never())->method('create');

        $factory = new DomainFilteringSitemapHandleFactory($inner, [ 'Devable.me' ]);

        $handle = $factory->create(
            $this->createStub(FilesystemOperator::class),
            $this->createStub(SalesChannelContext::class),
            'https://devable.me',
            'domain-id',
        );

        static::assertInstanceOf(NullSitemapHandle::class, $handle);
    }
}
