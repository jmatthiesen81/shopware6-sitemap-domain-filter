<?php

declare(strict_types=1);

namespace Devable\SitemapDomainFilterBundle;

/**
 * The configured hosts, matched case-insensitive and exact.
 */
readonly class ExcludedHosts
{
    /**
     * @var array<string, true>
     */
    private array $hosts;

    /**
     * @param list<string> $hosts e.g. "devable.me"
     */
    public function __construct(array $hosts)
    {
        $this->hosts = \array_fill_keys(\array_map(\strtolower(...), $hosts), true);
    }

    public function containsHost(string $host): bool
    {
        return isset($this->hosts[\strtolower($host)]);
    }

    /**
     * Checks the host of an absolute URL, e.g. a sales channel domain URL. URLs without a host never match.
     */
    public function containsUrl(string|null $url): bool
    {
        if (null === $url) {
            return false;
        }

        $host = \parse_url($url, \PHP_URL_HOST);

        if (!\is_string($host) || '' === $host) {
            return false;
        }

        return $this->containsHost($host);
    }
}
