<?php

declare(strict_types=1);

namespace Devable\SitemapDomainFilterBundle;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\Sitemap\Service\SitemapHandleFactoryInterface;
use Shopware\Core\Content\Sitemap\Service\SitemapHandleInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Returns a NullSitemapHandle for excluded hosts, so no sitemap file is written for them.
 */
readonly class DomainFilteringSitemapHandleFactory implements SitemapHandleFactoryInterface
{
    public function __construct(
        private SitemapHandleFactoryInterface $inner,
        private ExcludedHosts $excludedHosts,
    ) {
    }

    public function create(
        FilesystemOperator  $filesystem,
        SalesChannelContext $context,
        string|null $domain = null,
        string|null $domainId = null,
    ): SitemapHandleInterface {
        if ($this->excludedHosts->containsUrl($domain)) {
            return new NullSitemapHandle($filesystem, $context);
        }

        // The 6.6 interface declares only three parameters, the core factory reads $domainId via func_num_args().
        // So always pass all four arguments.
        return $this->inner->create($filesystem, $context, $domain, $domainId);
    }
}
