<?php

declare(strict_types=1);

namespace Devable\SitemapDomainFilterBundle\Tests;

use Devable\SitemapDomainFilterBundle\DomainFilteringSitemapHandleFactory;
use Devable\SitemapDomainFilterBundle\NullSitemapHandle;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Sitemap\Service\SitemapHandleFactory;
use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Runs real core SitemapHandles together with the NullSitemapHandle in the order of
 * SitemapExporter::finishSitemapHandles(): finish() on the first handle, finish(false) on all others.
 */
class CleanupOrderTest extends TestCase
{
    private const FOLDER = 'sitemap/salesChannel-sc-lang';

    public function testExcludedDomainAsFirstHandleCleansUpAndKeepsRealSitemap(): void
    {
        $paths = $this->generate(['https://devable.me' => 'devableId', 'https://netinventors.de' => 'netinventorsId']);

        static::assertSame([self::FOLDER . '/sc-netinventorsId-sitemap-netinventors-de-1.xml.gz'], $paths);
    }

    public function testExcludedDomainAsLaterHandleIsCleanedUpByCore(): void
    {
        $paths = $this->generate(['https://netinventors.de' => 'netinventorsId', 'https://devable.me' => 'devableId']);

        static::assertSame([self::FOLDER . '/sc-netinventorsId-sitemap-netinventors-de-1.xml.gz'], $paths);
    }

    /**
     * @param array<string, string> $domains url => domain id, in handle order
     *
     * @return list<string>
     */
    private function generate(array $domains): array
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        // Leftovers of a previous run, including the old sitemap of the excluded domain
        $filesystem->write(self::FOLDER . '/sc-devableId-sitemap-devable-me-1.xml.gz', 'old');
        $filesystem->write(self::FOLDER . '/sc-netinventorsId-sitemap-netinventors-de-1.xml.gz', 'old');

        $context = $this->createStub(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sc');
        $context->method('getLanguageId')->willReturn('lang');

        $factory = new DomainFilteringSitemapHandleFactory(new SitemapHandleFactory(new EventDispatcher()), ['devable.me']);

        $handles = [];
        foreach ($domains as $url => $domainId) {
            $handles[$url] = $factory->create($filesystem, $context, $url, $domainId);
        }

        static::assertInstanceOf(NullSitemapHandle::class, $handles['https://devable.me']);

        foreach ($handles as $url => $handle) {
            $sitemapUrl = new Url();
            $sitemapUrl->setLoc(\rtrim($url, '/') . '/page');
            $sitemapUrl->setLastmod(new \DateTimeImmutable('2026-01-01'));
            $sitemapUrl->setChangefreq('daily');
            $sitemapUrl->setPriority(0.5);
            $sitemapUrl->setResource('product');
            $sitemapUrl->setIdentifier('id');
            $handle->write([ $sitemapUrl ]);
        }

        foreach ($handles as $url => $handle) {
            if (\array_key_first($handles) === $url) {
                $handle->finish();

                continue;
            }

            $handle->finish(false);
        }

        $paths = [];
        foreach ($filesystem->listContents(self::FOLDER) as $file) {
            $paths[] = $file->path();
        }

        static::assertNotSame('old', $filesystem->read(self::FOLDER . '/sc-netinventorsId-sitemap-netinventors-de-1.xml.gz'));

        return $paths;
    }
}
