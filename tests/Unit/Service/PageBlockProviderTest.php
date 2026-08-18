<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Service;

use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use Nowo\PageLayoutKitBundle\Service\PageBlockProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PageBlockProviderTest extends KernelTestCase
{
    public function testLegacyHomeLayoutWhenNoCmsEntries(): void
    {
        self::bootKernel();
        /** @var PageBlockProvider $provider */
        $provider = self::getContainer()->get(PageBlockProvider::class);

        $layout = $provider->getLayout('home', 'es');
        self::assertNotEmpty($layout);
        self::assertSame(PageBlockType::Hero, $layout[0]->type);
        self::assertArrayHasKey('title', $layout[0]->data);

        $meta = $provider->pageMeta('home', 'es');
        self::assertArrayHasKey('title', $meta);
        self::assertArrayHasKey('description', $meta);

        self::assertSame($layout, $provider->getLayout('home', 'es'));
        $provider->reset();
        self::assertFalse($provider->hasLayout('home'));
    }

    public function testLegacyContactLayoutWhenNoCmsEntries(): void
    {
        self::bootKernel();
        /** @var PageBlockProvider $provider */
        $provider = self::getContainer()->get(PageBlockProvider::class);

        $layout = $provider->getLayout('contact', 'es');
        self::assertSame(PageBlockType::Compare, $layout[3]->type);
        self::assertArrayHasKey('beforeLabel', $layout[3]->data);
    }
}
