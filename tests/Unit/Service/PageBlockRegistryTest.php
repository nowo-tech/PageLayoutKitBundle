<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Service;

use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use Nowo\PageLayoutKitBundle\Service\PageBlockRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PageBlockRegistryTest extends KernelTestCase
{
    public function testGetReturnsNullForMissingIds(): void
    {
        self::bootKernel();
        /** @var PageBlockRegistry $registry */
        $registry = self::getContainer()->get(PageBlockRegistry::class);

        foreach (PageBlockType::cases() as $type) {
            self::assertNull($registry->get($type, 999_999));
        }
    }
}
