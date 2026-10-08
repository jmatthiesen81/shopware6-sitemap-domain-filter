# Shopware 6 Sitemap Domain Filter Bundle

Symfony bundle (not a Shopware plugin) that skips the sitemap generation and delivery for configured domains.

Shopware creates one sitemap per domain of a sales channel and language, and lists all of them in the same
`sitemap.xml` index. If two domains share a sales channel **and** a language, both sitemaps show up in that index.
This bundle prevents the sitemap of the excluded domains from being written, answers their `sitemap.xml` with a 404
and removes them from the `Sitemap:` lines of the `robots.txt`.

## Requirements

- PHP 8.2+
- Shopware 6.7 (`shopware/core ~6.7.0`)

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

Regenerate the sitemap and clear the HTTP cache afterwards, since `sitemap.xml` and `robots.txt` may be cached:

```bash
bin/console sitemap:generate --force
bin/console cache:clear
```

## How it works

The bundle decorates `Shopware\Core\Content\Sitemap\Service\SitemapHandleFactoryInterface`. For an excluded host it
returns a `NullSitemapHandle` that writes nothing, so no file exists that `SitemapLister` could list.

Shopware cleans up old sitemap files only through the first handle. If the excluded domain is first, the
`NullSitemapHandle` does that cleanup itself, so old files of the excluded domain are removed as well.

### Delivery

The core lists sitemaps per sales channel and language, not per domain. Without further measures,
`devable.me/sitemap.xml` would still serve an index that lists the sitemap files of the other domain. Therefore:

- `SitemapRequestSubscriber` answers the Storefront routes `frontend.sitemap.xml` and `frontend.sitemap.proxy` with
  an empty 404 if the request host is excluded. It runs on `kernel.request` before the sales channel context is
  resolved, so the 404 is not stored in the HTTP cache.
- `RobotsSitemapSubscriber` removes the `Sitemap:` lines of excluded hosts from the `robots.txt`. It is only
  registered if `RobotsPageLoadedEvent` exists (Storefront 6.7.1+).

The Store API route `/store-api/sitemap` is not affected, because it identifies the sales channel by access key, not
by domain. Headless frontends have to handle excluded domains themselves.

## SEO note

The excluded domain stays an active, crawlable storefront with the same content as the other domain. If it does not
have to stay one, removing it from the sales channel and redirecting it (301) is the cleaner solution.

## Maintenance

The bundle relies on internal behavior of `SitemapExporter` and `SitemapHandle`. On Shopware updates, check:

- `SitemapExporter::initSitemapHandles()` / `finishSitemapHandles()` (cleanup still only via the first handle?)
- `SitemapHandle::getPath()` / `cleanUp()` (folder scheme `sitemap/salesChannel-{sc}-{lang}/`)
- `SitemapHandleFactoryInterface::create()` signature and its service ID
- Route names in `Shopware\Storefront\Controller\SitemapController` (`frontend.sitemap.xml`, `frontend.sitemap.proxy`)
- `RobotsPageLoader::getSitemaps()` / `RobotsPage::setSitemaps()`

## Tests

```bash
composer install
composer test
```
