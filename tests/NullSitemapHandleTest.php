<?php

declare(strict_types=1);

namespace Devable\SitemapDomainFilterBundle\Tests;

use Devable\SitemapDomainFilterBundle\NullSitemapHandle;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\UnableToListContents;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class NullSitemapHandleTest extends TestCase
{
    private const SALES_CHANNEL_ID = 'sc';
    private const LANGUAGE_ID = 'lang';
    private const FOLDER = 'sitemap/salesChannel-sc-lang';

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->filesystem->write(self::FOLDER . '/sc-domain1-sitemap-netinventors-de-1.xml.gz', 'a');
        $this->filesystem->write(self::FOLDER . '/sc-domain2-sitemap-devable-me-1.xml.gz', 'b');
        $this->filesystem->write('sitemap/salesChannel-sc-other/sc-domain3-sitemap-other-1.xml.gz', 'c');
    }

    public function testWriteDoesNotCreateFiles(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects($this->never())->method('write');

        $handle = new NullSitemapHandle($filesystem, $this->createContext());
        $handle->write([]);
        $handle->finish(false);
    }

    public function testFinishEmptiesSalesChannelLanguageFolder(): void
    {
        $handle = new NullSitemapHandle($this->filesystem, $this->createContext());
        $handle->finish();

        static::assertSame([], $this->filesystem->listContents(self::FOLDER)->toArray());
        static::assertTrue($this->filesystem->fileExists('sitemap/salesChannel-sc-other/sc-domain3-sitemap-other-1.xml.gz'));
    }

    public function testFinishWithoutCleanUpLeavesFolderUntouched(): void
    {
        $handle = new NullSitemapHandle($this->filesystem, $this->createContext());
        $handle->finish(false);

        static::assertCount(2, $this->filesystem->listContents(self::FOLDER)->toArray());
    }

    public function testMissingFolderThrowsNothing(): void
    {
        // The in-memory adapter returns an empty listing for a missing folder, so a stub is needed to reach the catch
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('listContents')
            ->willThrowException(UnableToListContents::atLocation(self::FOLDER, false, new \RuntimeException('missing')))
        ;
        $filesystem->expects($this->never())->method('delete');

        $handle = new NullSitemapHandle($filesystem, $this->createContext());
        $handle->finish();

        $this->addToAssertionCount(1);
    }

    private function createContext(): SalesChannelContext
    {
        $context = $this->createStub(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn(self::SALES_CHANNEL_ID);
        $context->method('getLanguageId')->willReturn(self::LANGUAGE_ID);

        return $context;
    }
}
