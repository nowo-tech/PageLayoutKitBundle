<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Form;

use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Nowo\PageLayoutKitBundle\Entity\PageCardsBlock;
use Nowo\PageLayoutKitBundle\Entity\PageCompareBlock;
use Nowo\PageLayoutKitBundle\Entity\PageCtaBlock;
use Nowo\PageLayoutKitBundle\Entity\PageHeroBlock;
use Nowo\PageLayoutKitBundle\Entity\PageListBlock;
use Nowo\PageLayoutKitBundle\Entity\PageTextBlock;
use Nowo\PageLayoutKitBundle\Form\PageBlockLocalePanelType;
use Nowo\PageLayoutKitBundle\Form\PageCardsBlockInlineModalType;
use Nowo\PageLayoutKitBundle\Form\PageCompareBlockEditType;
use Nowo\PageLayoutKitBundle\Form\PageCompareBlockModalType;
use Nowo\PageLayoutKitBundle\Form\PageCtaBlockEditType;
use Nowo\PageLayoutKitBundle\Form\PageCtaBlockModalType;
use Nowo\PageLayoutKitBundle\Form\PageHeroBlockEditType;
use Nowo\PageLayoutKitBundle\Form\PageHeroBlockModalType;
use Nowo\PageLayoutKitBundle\Form\PageLayoutReorderData;
use Nowo\PageLayoutKitBundle\Form\PageLayoutReorderRowType;
use Nowo\PageLayoutKitBundle\Form\PageLayoutReorderType;
use Nowo\PageLayoutKitBundle\Form\PageListBlockInlineModalType;
use Nowo\PageLayoutKitBundle\Form\PageTextBlockEditType;
use Nowo\PageLayoutKitBundle\Form\PageTextBlockModalType;
use Nowo\PageLayoutKitBundle\Locale\PageLocales;
use Nowo\PageLayoutKitBundle\Tests\Support\LocaleTestSupport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\Yaml\Yaml;

use function array_unique;
use function basename;
use function glob;
use function is_string;
use function preg_match;
use function sort;
use function sprintf;

/**
 * Regression: every translation key the bundle forms render (label, help, placeholder — including
 * FormKit's auto `{form}.{field}.help` / `.placeholder` keys) must exist in every shipped catalogue,
 * so no raw key such as `page_layout_reorder.rows.help` reaches the admin UI.
 */
final class FormTranslationKeysTest extends TestCase
{
    private const string DOMAIN = 'NowoPageLayoutKitBundle';

    protected function setUp(): void
    {
        LocaleTestSupport::bindDefaults();
    }

    /** @return iterable<string, array{string}> */
    public static function catalogueProvider(): iterable
    {
        $files = glob(__DIR__ . '/../../../src/Resources/translations/' . self::DOMAIN . '.*.yaml');
        self::assertNotFalse($files);
        foreach ($files as $file) {
            yield basename($file) => [$file];
        }
    }

    #[DataProvider('catalogueProvider')]
    public function testEveryRenderedFormKeyIsTranslated(string $catalogueFile): void
    {
        $catalogue = Yaml::parseFile($catalogueFile);
        self::assertIsArray($catalogue);

        $missing = [];
        foreach ($this->collectKeys() as $key) {
            if (!isset($catalogue[$key])) {
                $missing[] = $key;
            }
        }

        self::assertSame([], $missing, sprintf('Missing keys in %s', basename($catalogueFile)));
    }

    public function testReorderFormWithoutRowsHasNoHelp(): void
    {
        $view = $this->factory()->create(PageLayoutReorderType::class, new PageLayoutReorderData())->createView();

        self::assertNull($view['rows']->vars['help']);
    }

    /** @return list<string> */
    private function collectKeys(): array
    {
        $factory = $this->factory();
        $locales = new PageLocales('es', ['es', 'en']);

        $hero = new PageHeroBlock();
        $hero->ensureTranslations($locales);
        $text = new PageTextBlock();
        $text->ensureTranslations($locales);
        $cta = new PageCtaBlock();
        $cta->ensureTranslations($locales);
        $compare = new PageCompareBlock();
        $compare->ensureTranslations($locales);

        $forms = [
            $factory->create(PageLayoutReorderType::class, new PageLayoutReorderData()),
            $factory->create(PageLayoutReorderRowType::class),
            $factory->create(PageHeroBlockModalType::class, $hero),
            $factory->create(PageTextBlockModalType::class, $text, ['include_meta' => true]),
            $factory->create(PageCtaBlockModalType::class, $cta),
            $factory->create(PageCompareBlockModalType::class, $compare),
            $factory->create(PageHeroBlockEditType::class),
            $factory->create(PageTextBlockEditType::class, null, ['include_meta' => true]),
            $factory->create(PageCtaBlockEditType::class),
            $factory->create(PageCompareBlockEditType::class),
            $factory->create(PageBlockLocalePanelType::class),
            $factory->create(PageCardsBlockInlineModalType::class, null, ['block' => new PageCardsBlock()]),
            $factory->create(PageListBlockInlineModalType::class, null, ['block' => new PageListBlock()]),
        ];

        $keys = [];
        foreach ($forms as $form) {
            $this->collectFromView($form->createView(), $keys);
        }

        $keys = array_unique($keys);
        sort($keys);

        return $keys;
    }

    /** @param list<string> $keys */
    private function collectFromView(FormView $view, array &$keys): void
    {
        if (($view->vars['translation_domain'] ?? null) === self::DOMAIN) {
            foreach ([$view->vars['label'] ?? null, $view->vars['help'] ?? null, $view->vars['attr']['placeholder'] ?? null] as $value) {
                if (is_string($value) && preg_match('/^[a-z0-9_]+(\.[a-z0-9_]+)+$/', $value) === 1) {
                    $keys[] = $value;
                }
            }
        }

        foreach ($view->children as $child) {
            $this->collectFromView($child, $keys);
        }
    }

    private function factory(): FormFactoryInterface
    {
        $merger = new FormOptionsMerger([
            // Mirrors the profile prepended by NowoPageLayoutKitExtension (auto help/placeholder on).
            'page_layout_kit' => [
                'translation_domain' => self::DOMAIN,
                'defaults'           => [
                    'attr'     => ['class' => 'nowo-ui-input form-control'],
                    'row_attr' => ['class' => 'mb-2'],
                ],
                'field_types' => [],
            ],
        ], 'page_layout_kit', new ConstraintDefinitionFactory());
        $map = new FormTypeMap();

        $types = [];
        foreach ([
            PageLayoutReorderType::class,
            PageLayoutReorderRowType::class,
            PageHeroBlockModalType::class,
            PageTextBlockModalType::class,
            PageCtaBlockModalType::class,
            PageCompareBlockModalType::class,
            PageHeroBlockEditType::class,
            PageTextBlockEditType::class,
            PageCtaBlockEditType::class,
            PageCompareBlockEditType::class,
            PageBlockLocalePanelType::class,
            PageCardsBlockInlineModalType::class,
            PageListBlockInlineModalType::class,
        ] as $class) {
            $types[] = new $class($merger, $map);
        }

        return Forms::createFormFactoryBuilder()->addTypes($types)->getFormFactory();
    }
}
