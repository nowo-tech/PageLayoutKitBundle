<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Model;

interface LocaleAwareTranslationInterface
{
    public function getLocale(): string;

    public function setLocale(string $locale): static;
}
