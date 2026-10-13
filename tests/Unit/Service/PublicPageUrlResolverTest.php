<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Service;

use Nowo\PageLayoutKitBundle\Service\PublicPageUrlResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PublicPageUrlResolverTest extends TestCase
{
    public function testMappedRouteWins(): void
    {
        self::assertSame('/', (new PublicPageUrlResolver($this->urls(['app_home' => '/']), ['home' => 'app_home']))->resolve('home'));
    }

    public function testUnmappedPageKeyTriesRouteWithSameName(): void
    {
        self::assertSame('/contact', (new PublicPageUrlResolver($this->urls(['contact' => '/contact'])))->resolve('contact'));
    }

    public function testMissingRouteIsNull(): void
    {
        // Regression: hosts without a "home" route got a 500 on the layout admin.
        self::assertNull((new PublicPageUrlResolver($this->urls([])))->resolve('home'));
    }

    public function testRouteNeedingParametersIsNull(): void
    {
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willThrowException(new MissingMandatoryParametersException('page', ['slug']));

        self::assertNull((new PublicPageUrlResolver($urls))->resolve('page'));
    }

    public function testEmptyMappingDisablesLink(): void
    {
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects(self::never())->method('generate');

        self::assertNull((new PublicPageUrlResolver($urls, ['home' => '']))->resolve('home'));
    }

    public function testResolveFirst(): void
    {
        $resolver = new PublicPageUrlResolver($this->urls(['contact' => '/contact']));

        self::assertSame('/contact', $resolver->resolveFirst(['home', 'contact']));
        self::assertNull($resolver->resolveFirst(['home']));
    }

    /**
     * @param array<string, string> $routes
     */
    private function urls(array $routes): UrlGeneratorInterface
    {
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(
            static fn (string $name): string => $routes[$name] ?? throw new RouteNotFoundException($name),
        );

        return $urls;
    }
}
