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
    /**
     * @var array<string, true>
     */
    private array $excludedHosts;

    /**
     * @param list<string> $excludedHosts lowercased hosts, e.g. "devable.me"
     */
    public function __construct(
        private SitemapHandleFactoryInterface $inner,
        array $excludedHosts,
    ) {
        $this->excludedHosts = \array_fill_keys(\array_map(\strtolower(...), $excludedHosts), true);
    }

    public function create(
        FilesystemOperator  $filesystem,
        SalesChannelContext $context,
        string|null $domain = null,
        string|null $domainId = null,
    ): SitemapHandleInterface {
        if ($this->isExcluded($domain)) {
            return new NullSitemapHandle($filesystem, $context);
        }

        // Always pass all four arguments, the core factory reads $domainId via func_num_args()
        return $this->inner->create($filesystem, $context, $domain, $domainId);
    }

    private function isExcluded(string|null $domain): bool
    {
        if (null === $domain) {
            return false;
        }

        $host = \parse_url($domain, \PHP_URL_HOST);

        if (!\is_string($host) || '' === $host) {
            return false;
        }

        return isset($this->excludedHosts[\strtolower($host)]);
    }
}
