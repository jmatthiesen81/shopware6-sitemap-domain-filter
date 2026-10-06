# Shopware 6 Sitemap Domain Filter Bundle

Symfony bundle (not a Shopware plugin) that skips the sitemap generation for configured domains.

Shopware creates one sitemap per domain of a sales channel and language, and lists all of them in the same
`sitemap.xml` index. If two domains share a sales channel **and** a language, both sitemaps show up in that index.
This bundle prevents the sitemap of the excluded domains from being written.

## Requirements

- PHP 8.2+
- Shopware 6.6 (`shopware/core ~6.6.0`)

## Installation

```bash
composer require devable/shopware6-sitemap-domain-filter
```

There is no Flex recipe. Register the bundle in `config/bundles.php`:

```php
Devable\SitemapDomainFilterBundle\SitemapDomainFilterBundle::class => ['all' => true],
```

## Configuration

```yaml
# config/packages/devable_sitemap_domain_filter.yaml
devable_sitemap_domain_filter:
  excluded_domains:
    - devable.me
```

- Entries are hosts. Full URLs are accepted and reduced to the host (`https://Devable.me/` → `devable.me`).
- Matching is case-insensitive but **exact**: `www.devable.me` needs its own entry.
- A host excludes all of its paths (`devable.me/en` too) and ignores the port.
- With an empty list the bundle registers nothing.

Regenerate the sitemap afterwards:

```bash
bin/console sitemap:generate --force
```

## How it works

The bundle decorates `Shopware\Core\Content\Sitemap\Service\SitemapHandleFactoryInterface`. For an excluded host it
returns a `NullSitemapHandle` that writes nothing, so no file exists that `SitemapLister` could list.

Shopware cleans up old sitemap files only through the first handle. If the excluded domain is first, the
`NullSitemapHandle` does that cleanup itself, so old files of the excluded domain are removed as well.

## SEO note

`devable.me/sitemap.xml` uses the same sales channel and language, so it still serves an index that lists only the
sitemap files of the other domain. If the excluded domain does not have to stay an active storefront, removing it
from the sales channel and redirecting it (301) is the cleaner solution.

## Maintenance

The bundle relies on internal behavior of `SitemapExporter` and `SitemapHandle`. On Shopware updates, check:

- `SitemapExporter::initSitemapHandles()` / `finishSitemapHandles()` (cleanup still only via the first handle?)
- `SitemapHandle::getPath()` / `cleanUp()` (folder scheme `sitemap/salesChannel-{sc}-{lang}/`)
- `SitemapHandleFactoryInterface::create()` signature and its service ID

## Tests

```bash
composer install
composer test
```
