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
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
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
        $this->generate(['https://devable.me' => 'devableId', 'https://netinventors.de' => 'netinventorsId']);
    }

    public function testExcludedDomainAsLaterHandleIsCleanedUpByCore(): void
    {
        $this->generate(['https://netinventors.de' => 'netinventorsId', 'https://devable.me' => 'devableId']);
    }

    /**
     * Asserts that only a freshly written sitemap of the non-excluded domain is left.
     *
     * The file name depends on the Shopware version: early 6.6 releases write "sc-sitemap-{domain}-1.xml.gz",
     * later ones add the domain id ("sc-{domainId}-sitemap-{domain}-1.xml.gz"). So only the suffix is checked.
     *
     * @param array<string, string> $domains url => domain id, in handle order
     */
    private function generate(array $domains): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        // Leftovers of a previous run in both naming schemes, including the old sitemap of the excluded domain
        $filesystem->write(self::FOLDER . '/sc-devableId-sitemap-devable-me-1.xml.gz', 'old');
        $filesystem->write(self::FOLDER . '/sc-netinventorsId-sitemap-netinventors-de-1.xml.gz', 'old');
        $filesystem->write(self::FOLDER . '/sc-sitemap-devable-me-1.xml.gz', 'old');
        $filesystem->write(self::FOLDER . '/sc-sitemap-netinventors-de-1.xml.gz', 'old');

        $context = $this->createStub(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn('sc');
        $context->method('getLanguageId')->willReturn('lang');
        // Early 6.6 releases build the folder and file names from the sales channel entity
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('sc');
        $context->method('getSalesChannel')->willReturn($salesChannel);

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

        static::assertCount(1, $paths);
        static::assertStringEndsWith('-sitemap-netinventors-de-1.xml.gz', $paths[0]);
        static::assertNotSame('old', $filesystem->read($paths[0]));
    }
}
