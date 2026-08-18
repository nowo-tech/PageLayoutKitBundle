<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Enum;

/**
 * Composable page block kinds used in home/contact layouts.
 */
enum PageBlockType: string
{
    case Hero    = 'hero';
    case Text    = 'text';
    case Cards   = 'cards';
    case List    = 'list';
    case Cta     = 'cta';
    case Compare = 'compare';

    public function isModalEditable(): bool
    {
        return true;
    }
}
