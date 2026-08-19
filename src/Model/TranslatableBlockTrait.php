<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Model;

use Doctrine\Common\Collections\Collection;
use Nowo\PageLayoutKitBundle\Locale\PageLocales;

/**
 * @template T of LocaleAwareTranslationInterface
 */
trait TranslatableBlockTrait
{
    /** @return Collection<int, T> */
    abstract public function getTranslations(): Collection;

    /** @param T $translation */
    abstract public function addTranslation(object $translation): self;

    /** @return T|null */
    public function getTranslation(string $locale): ?object
    {
        foreach ($this->getTranslations() as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation;
            }
        }

        return null;
    }

    /** @return T */
    public function getTranslationOrFallback(string $locale): object
    {
        $translation = $this->getTranslation($locale)
            ?? $this->getTranslation(PageLocales::default());

        if ($translation !== null) {
            return $translation;
        }

        $first = $this->getTranslations()->first();

        if ($first !== false) {
            return $first;
        }

        $class = $this->translationClass();

        return new $class();
    }

    public function ensureTranslations(): self
    {
        foreach (PageLocales::all() as $locale) {
            if ($this->getTranslation($locale) === null) {
                $class = $this->translationClass();
                $this->addTranslation((new $class())->setLocale($locale));
            }
        }

        return $this;
    }

    /** @return class-string<T> */
    abstract protected function translationClass(): string;
}
