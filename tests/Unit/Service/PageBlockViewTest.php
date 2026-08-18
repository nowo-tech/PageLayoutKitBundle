<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Service;

use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use Nowo\PageLayoutKitBundle\Service\PageBlockView;
use PHPUnit\Framework\TestCase;

final class PageBlockViewTest extends TestCase
{
    public function testTemplateAndModalFlags(): void
    {
        $view = new PageBlockView(
            layoutId: 7,
            pageKey: 'home',
            type: PageBlockType::Hero,
            blockId: 3,
            sectionKey: null,
            data: ['title' => 'Hello'],
        );

        self::assertSame(7, $view->layoutId);
        self::assertSame('home', $view->pageKey);
        self::assertSame(PageBlockType::Hero, $view->type);
        self::assertSame(3, $view->blockId);
        self::assertNull($view->sectionKey);
        self::assertSame(['title' => 'Hello'], $view->data);
        self::assertTrue($view->isModalEditable());
        self::assertSame('pages/blocks/hero.html.twig', $view->templateName());
    }
}
