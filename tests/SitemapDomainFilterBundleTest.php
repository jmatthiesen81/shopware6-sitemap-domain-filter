<?php

declare(strict_types=1);

namespace Devable\SitemapDomainFilterBundle\Tests;

use Devable\SitemapDomainFilterBundle\DomainFilteringSitemapHandleFactory;
use Devable\SitemapDomainFilterBundle\ExcludedHosts;
use Devable\SitemapDomainFilterBundle\RobotsSitemapSubscriber;
use Devable\SitemapDomainFilterBundle\SitemapDomainFilterBundle;
use Devable\SitemapDomainFilterBundle\SitemapRequestSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Sitemap\Service\SitemapHandleFactoryInterface;
use Shopware\Core\Content\Sitemap\Service\SitemapHandleInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

class SitemapDomainFilterBundleTest extends TestCase
{
    public function testExtensionAlias(): void
    {
        static::assertSame('devable_sitemap_domain_filter', $this->getExtension()->getAlias());
    }

    public function testEmptyConfigDoesNotRegisterDecorator(): void
    {
        $builder = $this->load([]);

        static::assertFalse($builder->hasDefinition(DomainFilteringSitemapHandleFactory::class));
        static::assertFalse($builder->hasDefinition(SitemapRequestSubscriber::class));
        static::assertFalse($builder->hasDefinition(RobotsSitemapSubscriber::class));
    }

    public function testDecoratorIsRegisteredWithNormalizedHosts(): void
    {
        $builder = $this->load([ 'excluded_domains' => [ ' Devable.me ', 'https://www.devable.me/', 'devable.me' ] ]);

        $definition = $builder->getDefinition(DomainFilteringSitemapHandleFactory::class);

        static::assertSame(SitemapHandleFactoryInterface::class, $definition->getDecoratedService()[0] ?? null);
        static::assertSame([ 'devable.me', 'www.devable.me' ], $builder->getDefinition(ExcludedHosts::class)->getArgument(0));
    }

    public function testSubscribersAreRegistered(): void
    {
        $builder = $this->load([ 'excluded_domains' => [ 'devable.me' ] ]);

        static::assertTrue($builder->getDefinition(SitemapRequestSubscriber::class)->hasTag('kernel.event_subscriber'));
        static::assertTrue($builder->getDefinition(RobotsSitemapSubscriber::class)->hasTag('kernel.event_subscriber'));
    }

    public function testDecoratorReplacesCoreFactoryAfterCompile(): void
    {
        $builder = $this->load([ 'excluded_domains' => [ 'devable.me' ] ]);
        $builder->register(SitemapHandleFactoryInterface::class, InnerFactoryStub::class)->setPublic(true);
        $builder->compile();

        static::assertInstanceOf(
            DomainFilteringSitemapHandleFactory::class,
            $builder->get(SitemapHandleFactoryInterface::class),
        );
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidConfigProvider(): iterable
    {
        yield 'not a list' => [ [ 'excluded_domains' => 'devable.me' ] ];
        yield 'nested array' => [ [ 'excluded_domains' => [ [ 'devable.me' ] ] ] ];
        yield 'integer' => [ [ 'excluded_domains' => [ 42 ] ] ];
        yield 'empty string' => [ [ 'excluded_domains' => [ '  ' ] ] ];
        yield 'no host' => [ [ 'excluded_domains' => [ 'https://' ] ] ];
    }

    #[DataProvider('invalidConfigProvider')]
    public function testInvalidConfigIsRejected(mixed $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load($config);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function normalizeHostProvider(): iterable
    {
        yield 'plain' => [ 'devable.me', 'devable.me' ];
        yield 'whitespace and case' => [ '  DevAble.ME ', 'devable.me' ];
        yield 'full url' => [ 'https://devable.me/en/', 'devable.me' ];
        yield 'with port' => [ 'devable.me:8443', 'devable.me' ];
        yield 'host with path' => [ 'devable.me/en', 'devable.me' ];
        yield 'empty' => [ '', '' ];
    }

    #[DataProvider('normalizeHostProvider')]
    public function testNormalizeHost(string $value, string $expected): void
    {
        static::assertSame($expected, SitemapDomainFilterBundle::normalizeHost($value));
    }

    private function getExtension(): ExtensionInterface
    {
        $extension = (new SitemapDomainFilterBundle())->getContainerExtension();
        static::assertNotNull($extension);

        return $extension;
    }

    private function load(mixed $config): ContainerBuilder
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('kernel.environment', 'test');
        $builder->setParameter('kernel.build_dir', \sys_get_temp_dir());
        $this->getExtension()->load([ $config ], $builder);

        return $builder;
    }
}

/**
 * @internal
 */
class InnerFactoryStub implements SitemapHandleFactoryInterface
{
    public function create(
        \League\Flysystem\FilesystemOperator $filesystem,
        \Shopware\Core\System\SalesChannel\SalesChannelContext $context,
        string|null $domain = null,
        string|null $domainId = null,
    ): SitemapHandleInterface {
        throw new \LogicException('Not used');
    }
}
