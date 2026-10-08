<?php

declare(strict_types=1);

namespace Devable\SitemapDomainFilterBundle;

use Shopware\Core\Content\Sitemap\Service\SitemapHandleFactoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

class SitemapDomainFilterBundle extends AbstractBundle
{
    /**
     * Set explicitly, the alias derived from the class name would be "sitemap_domain_filter".
     */
    protected string $extensionAlias = 'devable_sitemap_domain_filter';

    public function configure(DefinitionConfigurator $definition): void
    {
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $definition->rootNode();

        $rootNode
            ->children()
                ->arrayNode('excluded_domains')
                    ->info('Hosts whose sitemap is neither generated nor delivered, e.g. "devable.me". Matched exactly, "www." variants need their own entry.')
                    ->defaultValue([])
                    ->scalarPrototype()
                        ->beforeNormalization()
                            ->ifString()
                            ->then(static fn (string $value): string => self::normalizeHost($value))
                        ->end()
                        ->validate()
                            ->ifTrue(static fn (mixed $value): bool => !\is_string($value))
                            ->thenInvalid('Excluded domain must be a string, got %s.')
                        ->end()
                        ->cannotBeEmpty()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * @param array{excluded_domains: list<string>} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $excludedHosts = \array_values(\array_unique($config['excluded_domains']));

        if ([] === $excludedHosts) {
            return;
        }

        $services = $configurator->services();

        $services
            ->set(ExcludedHosts::class)
            ->args([ $excludedHosts ])
        ;

        $services
            ->set(DomainFilteringSitemapHandleFactory::class)
            ->decorate(SitemapHandleFactoryInterface::class)
            ->args([
                service('.inner'),
                service(ExcludedHosts::class),
            ])
        ;

        $services
            ->set(SitemapRequestSubscriber::class)
            ->args([ service(ExcludedHosts::class) ])
            ->tag('kernel.event_subscriber')
        ;
    }

    /**
     * Reduces an entry to its lowercased host, so "https://Devable.me/" becomes "devable.me".
     * Returns an empty string for values without a host, which the config validation rejects.
     */
    public static function normalizeHost(string $value): string
    {
        $value = \strtolower(\trim($value));

        if ('' === $value) {
            return '';
        }

        if (!\str_contains($value, '://')) {
            $value = 'http://' . $value;
        }

        $host = \parse_url($value, \PHP_URL_HOST);

        return \is_string($host) ? $host : '';
    }
}
