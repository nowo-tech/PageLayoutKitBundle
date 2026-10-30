<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Twig;

use Dom\Element;
use Dom\HTMLDocument;
use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\Extension\LocaleTabsExtension;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Nowo\FormKitBundle\Twig\LocaleTabsTwigExtension;
use Nowo\PageLayoutKitBundle\Entity\PageHeroBlock;
use Nowo\PageLayoutKitBundle\Form\PageHeroBlockEditType;
use Nowo\PageLayoutKitBundle\Form\PageHeroBlockModalType;
use Nowo\PageLayoutKitBundle\Locale\PageLocales;
use Nowo\PageLayoutKitBundle\Security\PageLayoutKitAccessCheckerInterface;
use Nowo\PageLayoutKitBundle\Twig\PageLayoutKitExtension;
use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Twig\ThemeExtension;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\Forms;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

use function array_map;
use function array_values;
use function dirname;
use function iterator_to_array;
use function trim;

use const LIBXML_NOERROR;

/**
 * Regression: the block edit modal used host templates (components/admin/_locale_tabs.html.twig,
 * form/auth_theme.html.twig) and failed in hosts that do not ship them. It must render with bundle
 * (and FormKit / UiKit) templates only; translations render as FormKit locale tabs (mode entries).
 */
final class ModalFormTemplateTest extends TestCase
{
    private const string DOMAIN = 'NowoPageLayoutKitBundle';

    public function testModalRendersFormKitLocaleTabsWithTheDefaultLocaleFirst(): void
    {
        $html = $this->render('en', ['en', 'es']);
        $doc  = HTMLDocument::createFromString('<!doctype html><body>' . $html, LIBXML_NOERROR);

        self::assertStringContainsString('id="cms-modal-active-form"', $html);
        $root = $doc->querySelector('[data-testid="page-layout-kit-locale-tabs"] #cms-locale[data-nowo-ui-locale-tabs]');
        self::assertInstanceOf(Element::class, $root);
        self::assertSame('Languages', $doc->querySelector('[role="tablist"]')?->getAttribute('aria-label'));
        self::assertSame(['es', 'en'], $this->tabLocales($doc), 'default locale (es) first');
        self::assertSame('cms-locale-en', $root->getAttribute('data-nowo-ui-tabs-active'), 'the UI locale opens');
        $es = $doc->querySelector('#tab-cms-locale-es');
        self::assertTrue($es?->hasAttribute('data-default') ?? false);
        self::assertTrue($es->hasAttribute('data-required'));
        self::assertNull($doc->querySelector('#tab-cms-locale-en[data-required]'));
        self::assertStringContainsString('Spanish', $es->textContent ?? '');
        self::assertSame('es', $doc->querySelector('#tab-cms-locale-es-panel input[type="hidden"][name$="[locale]"]')?->getAttribute('value'));
        self::assertNotNull($doc->querySelector('#tab-cms-locale-en-panel input[name$="[title]"]'));
        self::assertCount(0, $doc->querySelectorAll('input[type="text"][name$="[locale]"]'));
        self::assertStringContainsString('Eyebrow', $html);
        self::assertStringContainsString('Primary button text', $html);
        self::assertStringNotContainsString('page_hero_block_edit.', $html);
        self::assertStringNotContainsString('admin-locale-tabs', $html);
        self::assertStringNotContainsString('data-controller="locale-tabs"', $html);
        self::assertStringNotContainsString('locale.es', $html);
        self::assertStringNotContainsString('<script', $html);
    }

    public function testLocaleTabsFallBackToTheBareCodeWithoutAName(): void
    {
        $html = $this->render('de', ['es', 'xx']);
        $doc  = HTMLDocument::createFromString('<!doctype html><body>' . $html, LIBXML_NOERROR);

        self::assertSame('Sprachen', $doc->querySelector('[role="tablist"]')?->getAttribute('aria-label'));
        self::assertStringContainsString('Spanisch', $doc->querySelector('#tab-cms-locale-es')->textContent ?? '');
        self::assertSame('XX', trim($doc->querySelector('#tab-cms-locale-xx .nowo-ui-locale-tabs__code')->textContent ?? ''));
        self::assertStringNotContainsString('locale.xx', $html);
    }

    public function testInvalidLocaleTabIsMarkedAndOpened(): void
    {
        $html = $this->render('es', ['es', 'en'], static function (FormInterface $form): void {
            $form->get('translations')->get('1')->get('title')->addError(new FormError('Title missing.'));
        });
        $doc = HTMLDocument::createFromString('<!doctype html><body>' . $html, LIBXML_NOERROR);

        self::assertSame('cms-locale-en', $doc->querySelector('#cms-locale')?->getAttribute('data-nowo-ui-tabs-active'));
        self::assertTrue($doc->querySelector('#tab-cms-locale-en')?->hasAttribute('data-invalid') ?? false);
        self::assertNull($doc->querySelector('#tab-cms-locale-es[data-invalid]'));
        self::assertSame('true', $doc->querySelector('#tab-cms-locale-en-panel input[name$="[title]"]')?->getAttribute('aria-invalid'));
        self::assertStringContainsString('Title missing.', $doc->querySelector('#tab-cms-locale-en-panel')->textContent ?? '');
    }

    public function testModalFormThemesOnlyListInstalledOptionalThemes(): void
    {
        $extension = $this->extension();

        self::assertSame([], $extension->modalFormThemes(new Environment(new ArrayLoader())));
        self::assertSame(
            ['@NowoCkeditor5EditorBundle/Form/ckeditor5_editor_theme.html.twig'],
            $extension->modalFormThemes(new Environment(new ArrayLoader([
                '@NowoCkeditor5EditorBundle/Form/ckeditor5_editor_theme.html.twig' => '',
            ]))),
        );
    }

    /**
     * @param list<string> $locales
     * @param (callable(FormInterface<mixed>): void)|null $alter
     */
    private function render(string $uiLocale, array $locales = ['es', 'en'], ?callable $alter = null): string
    {
        $root  = dirname(__DIR__, 3);
        $views = new FilesystemLoader();
        $views->addPath($root . '/src/Resources/views', self::DOMAIN);
        $views->addPath($root . '/vendor/nowo-tech/form-kit-bundle/src/Resources/views', 'NowoFormKitBundle');
        $views->addPath($root . '/vendor/nowo-tech/ui-kit-bundle/src/Resources/views', 'NowoUiKitBundle');
        $views->addPath($root . '/vendor/nowo-tech/ui-kit-bundle/src/Resources/views', 'NowoUiKit');
        $bridgeViews = dirname((string) (new ReflectionClass(FormExtension::class))->getFileName(), 2) . '/Resources/views/Form';
        $views->addPath($bridgeViews);

        // Only bundle, FormKit / UiKit and Symfony templates: no components/admin/* or form/auth_theme.html.twig.
        $twig = new Environment(new ChainLoader([$views]), ['strict_variables' => true]);

        $translator = new Translator($uiLocale);
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource(
            'yaml',
            dirname(__DIR__, 3) . '/src/Resources/translations/' . self::DOMAIN . '.' . $uiLocale . '.yaml',
            $uiLocale,
            self::DOMAIN,
        );
        $translator->addResource('yaml', $root . '/vendor/nowo-tech/ui-kit-bundle/src/Resources/translations/NowoUiKitBundle.' . $uiLocale . '.yaml', $uiLocale, 'NowoUiKitBundle');

        $engine = new TwigRendererEngine(['form_div_layout.html.twig'], $twig);
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            FormRenderer::class => static fn (): FormRenderer => new FormRenderer($engine),
        ]));
        $twig->addExtension(new FormExtension());
        $twig->addExtension(new TranslationExtension($translator));
        $twig->addExtension($this->extension());
        $twig->addExtension(new LocaleTabsTwigExtension());
        $twig->addExtension(new ThemeExtension(new Theme(), translator: $translator));

        $hero = new PageHeroBlock();
        $hero->ensureTranslations(new PageLocales($locales[0], $locales));

        $merger = new FormOptionsMerger([
            'page_layout_kit' => [
                'translation_domain' => self::DOMAIN,
                'defaults'           => [
                    'attr'     => ['class' => 'nowo-ui-input form-control'],
                    'row_attr' => ['class' => 'mb-2'],
                ],
                'field_types' => [],
            ],
        ], 'page_layout_kit', new ConstraintDefinitionFactory());
        $factory = Forms::createFormFactoryBuilder()
            ->addTypeExtension(new LocaleTabsExtension())
            ->addTypes([
                new PageHeroBlockModalType($merger, new FormTypeMap()),
                new PageHeroBlockEditType($merger, new FormTypeMap()),
            ])
            ->getFormFactory();
        $form = $factory->create(PageHeroBlockModalType::class, $hero, ['action' => '/admin/page-blocks/hero/1']);
        if ($alter !== null) {
            $form->submit(['translations' => [['locale' => 'es', 'title' => 'Hola'], ['locale' => 'en', 'title' => 'x']]], false);
            $alter($form);
        }

        return $twig->render('@NowoPageLayoutKitBundle/admin/_modal_form.html.twig', [
            'form'   => $form->createView(),
            'locale' => $uiLocale,
        ]);
    }

    /** @return list<string> */
    private function tabLocales(HTMLDocument $doc): array
    {
        return array_values(array_map(static fn (Element $e): string => (string) $e->getAttribute('data-locale'), iterator_to_array($doc->querySelectorAll('[role="tab"]'))));
    }

    private function extension(): PageLayoutKitExtension
    {
        return new PageLayoutKitExtension(
            '@NowoPageLayoutKitBundle/admin/layout.html.twig',
            'bootstrap5',
            ['home'],
            'es',
            $this->createStub(PageLayoutKitAccessCheckerInterface::class),
        );
    }
}
