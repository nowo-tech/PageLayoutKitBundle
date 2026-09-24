<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Twig;

use Nowo\PageLayoutKitBundle\Security\PageLayoutKitAccessCheckerInterface;
use Nowo\PageLayoutKitBundle\Twig\PageLayoutKitExtension;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

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

    public function testCanEditFunctionIsEvaluatedPerRenderOnTheSameTwigEnvironment(): void
    {
        $currentUserIsEditor = true;
        $accessChecker       = $this->createStub(PageLayoutKitAccessCheckerInterface::class);
        $accessChecker->method('canAccess')->willReturnCallback(
            static function () use (&$currentUserIsEditor): bool {
                return $currentUserIsEditor;
            },
        );

        $extension = new PageLayoutKitExtension('layout.html.twig', 'tailwind', ['home'], 'es', $accessChecker);
        self::assertSame('nowo_page_layout_kit_can_edit', $extension->getFunctions()[0]->getName());

        $twig = new Environment(new ArrayLoader([
            'pencil' => '{{ nowo_page_layout_kit_can_edit() ? "pencil" : "none" }}',
        ]));
        $twig->addExtension($extension);

        self::assertSame('pencil', $twig->render('pencil'), 'Request 1: editor.');

        $currentUserIsEditor = false;

        self::assertSame('none', $twig->render('pencil'), 'Request 2: anonymous visitor, no Twig reset in between.');
    }
}
