<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Twig;

use Nowo\PageLayoutKitBundle\Security\PageLayoutKitAccessCheckerInterface;
use Nowo\PageLayoutKitBundle\Twig\PageLayoutKitExtension;
use PHPUnit\Framework\TestCase;

final class PageLayoutKitExtensionTest extends TestCase
{
    public function testGlobalsExposeConfiguredBundleState(): void
    {
        $accessChecker = $this->createMock(PageLayoutKitAccessCheckerInterface::class);
        $accessChecker->expects(self::once())
            ->method('canAccess')
            ->willReturn(true);

        $extension = new PageLayoutKitExtension(
            '@NowoPageLayoutKitBundle/admin/layout.html.twig',
            'tailwind',
            ['home', 'contact'],
            'es',
            $accessChecker,
        );

        self::assertSame(
            [
                'nowo_page_layout_kit_layout'         => '@NowoPageLayoutKitBundle/admin/layout.html.twig',
                'nowo_page_layout_kit_css_framework'  => 'tailwind',
                'nowo_page_layout_kit_pages'          => ['home', 'contact'],
                'nowo_page_layout_kit_default_locale' => 'es',
                'nowo_page_layout_kit_can_edit'       => true,
            ],
            $extension->getGlobals(),
        );
    }
}
