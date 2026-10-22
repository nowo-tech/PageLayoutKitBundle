<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Translation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

use function in_array;

/**
 * Every shipped locale has the same keys and placeholders as English, and real translations
 * (no English copies except for words that are identical in that language).
 */
final class TranslationParityTest extends TestCase
{
    private const string DIR = __DIR__ . '/../../../src/Resources/translations';

    /**
     * Keys whose English value is also the correct translation in that locale.
     */
    private const array SAME_AS_ENGLISH = [
        'es' => ['admin.layout.id'],
        'de' => ['admin.layout.title', 'admin.layout.position', 'admin.layout.id'],
        'fr' => ['admin.layout.position', 'admin.layout.type', 'admin.layout.id', 'admin.layout.actions'],
        'it' => ['admin.layout.title', 'admin.layout.id'],
        'nl' => ['admin.layout.type', 'admin.layout.id'],
        'pt' => ['admin.layout.title', 'admin.layout.id'],
    ];

    /**
     * @return iterable<string, array{string}>
     */
    public static function locales(): iterable
    {
        foreach (array_keys(self::SAME_AS_ENGLISH) as $locale) {
            yield $locale => [$locale];
        }
    }

    public function testAllSevenLocalesAreShipped(): void
    {
        $files = glob(self::DIR . '/NowoPageLayoutKitBundle.*.yaml');
        self::assertIsArray($files);
        $locales = array_map(static fn (string $f): string => explode('.', basename($f))[1], $files);
        sort($locales);

        self::assertSame(['de', 'en', 'es', 'fr', 'it', 'nl', 'pt'], $locales);
    }

    #[DataProvider('locales')]
    public function testLocaleHasTheSameKeysAndPlaceholdersAsEnglish(string $locale): void
    {
        $en     = $this->load('en');
        $target = $this->load($locale);

        self::assertSame(array_keys($en), array_keys($target), $locale . ' keys differ from en');

        foreach ($en as $key => $value) {
            self::assertNotSame('', trim($target[$key]), $locale . ':' . $key . ' is empty');
            self::assertSame($this->placeholders($value), $this->placeholders($target[$key]), $locale . ':' . $key . ' placeholders');
        }
    }

    #[DataProvider('locales')]
    public function testLocaleValuesAreTranslatedNotEnglishCopies(string $locale): void
    {
        $en     = $this->load('en');
        $target = $this->load($locale);

        foreach ($en as $key => $value) {
            if (in_array($key, self::SAME_AS_ENGLISH[$locale], true)) {
                continue;
            }

            self::assertNotSame($value, $target[$key], $locale . ':' . $key . ' is an untranslated English copy');
        }
    }

    /**
     * @return array<string, string>
     */
    private function load(string $locale): array
    {
        /** @var array<string, string> $data */
        $data = Yaml::parseFile(self::DIR . '/NowoPageLayoutKitBundle.' . $locale . '.yaml');

        return $data;
    }

    /**
     * @return list<string>
     */
    private function placeholders(string $value): array
    {
        preg_match_all('/%[a-z_]+%/', $value, $matches);
        $found = $matches[0];
        sort($found);

        return $found;
    }
}
