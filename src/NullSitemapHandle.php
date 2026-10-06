<?php

declare(strict_types=1);

namespace Devable\SitemapDomainFilterBundle;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\Sitemap\Service\SitemapHandleInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Sitemap handle for an excluded domain. Writes nothing, but still cleans up the sitemap folder
 * when it is the first handle, because the core only cleans up through the first handle
 * (see SitemapExporter::finishSitemapHandles()).
 */
readonly class NullSitemapHandle implements SitemapHandleInterface
{
    public function __construct(
        private FilesystemOperator $filesystem,
        private SalesChannelContext $context,
    ) {
    }

    public function write(array $urls): void
    {
    }

    public function finish(bool|null $cleanUp = true): void
    {
        if (!$cleanUp) {
            return;
        }

        // Same folder as SitemapHandle::getPath()
        $path = 'sitemap/salesChannel-' . $this->context->getSalesChannelId() . '-' . $this->context->getLanguageId();

        try {
            // Flysystem lists lazily, so the iteration has to happen inside the try
            foreach ($this->filesystem->listContents($path) as $file) {
                if ($file->isFile()) {
                    $this->filesystem->delete($file->path());
                }
            }
        } catch (\Throwable) {
            // Folder does not exist
        }
    }
}
