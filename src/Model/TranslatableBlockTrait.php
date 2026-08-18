<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Model;

use Nowo\PageLayoutKitBundle\Locale\PageLocales;
use Doctrine\Common\Collections\Collection;

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

        if (null !== $translation) {
            return $translation;
        }

        $first = $this->getTranslations()->first();

        if (false !== $first) {
            return $first;
        }

        $class = $this->translationClass();

        return new $class();
    }

    public function ensureTranslations(): self
    {
        foreach (PageLocales::all() as $locale) {
            if (null === $this->getTranslation($locale)) {
                $class = $this->translationClass();
                $this->addTranslation(new $class()->setLocale($locale));
            }
        }

        return $this;
    }

    /** @return class-string<T> */
    abstract protected function translationClass(): string;
}
