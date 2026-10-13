<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Twig;

use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Nowo\PageLayoutKitBundle\Entity\PageHeroBlock;
use Nowo\PageLayoutKitBundle\Form\PageHeroBlockEditType;
use Nowo\PageLayoutKitBundle\Form\PageHeroBlockModalType;
use Nowo\PageLayoutKitBundle\Locale\PageLocales;
use Nowo\PageLayoutKitBundle\Security\PageLayoutKitAccessCheckerInterface;
use Nowo\PageLayoutKitBundle\Tests\Support\LocaleTestSupport;
use Nowo\PageLayoutKitBundle\Twig\PageLayoutKitExtension;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\Forms;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

use function dirname;
use function file_put_contents;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

/**
 * Regression: the block edit modal used host templates (components/admin/_locale_tabs.html.twig,
 * form/auth_theme.html.twig) and failed in hosts that do not ship them. It must render with bundle
 * templates only, while a host override under templates/bundles/NowoPageLayoutKitBundle still wins.
 */
final class ModalFormTemplateTest extends TestCase
{
    private const string DOMAIN = 'NowoPageLayoutKitBundle';

    private ?string $overrideDir = null;

    protected function setUp(): void
    {
        LocaleTestSupport::bindDefaults();
    }

    protected function tearDown(): void
    {
        if ($this->overrideDir !== null) {
            new Filesystem()->remove($this->overrideDir);
        }
    }

    public function testModalRendersWithBundleTemplatesOnlyAndTranslatedLabels(): void
    {
        $html = $this->render('en');

        self::assertStringContainsString('id="cms-modal-active-form"', $html);
        self::assertStringContainsString('aria-label="Locales"', $html);
        self::assertStringContainsString('ES — Spanish', $html);
        self::assertStringContainsString('EN — English', $html);
        self::assertStringContainsString('Eyebrow', $html);
        self::assertStringContainsString('Primary button text', $html);
        self::assertStringNotContainsString('page_hero_block_edit.', $html);
        self::assertStringNotContainsString('admin.locale_tabs', $html);
        self::assertStringNotContainsString('locale.es', $html);
    }

    public function testLocaleTabsFallBackToTheBareCodeWithoutAName(): void
    {
        $html = $this->render('de', ['es', 'xx']);

        self::assertStringContainsString('aria-label="Sprachen"', $html);
        self::assertStringContainsString('ES — Spanisch', $html);
        self::assertMatchesRegularExpression('/>\s*XX\s*</', $html);
        self::assertStringNotContainsString('locale.xx', $html);
    }

    public function testHostCanOverrideTheLocaleTabsThroughBundleTemplateOverride(): void
    {
        $this->overrideDir = sys_get_temp_dir() . '/plk-override-' . uniqid('', true);
        mkdir($this->overrideDir . '/admin', 0o777, true);
        file_put_contents($this->overrideDir . '/admin/_locale_tabs.html.twig', '<div class="host-tabs">HOST TABS</div>');

        $html = $this->render('en', ['es', 'en'], $this->overrideDir);

        self::assertStringContainsString('HOST TABS', $html);
        self::assertStringNotContainsString('admin-locale-tabs', $html);
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
     */
    private function render(string $uiLocale, array $locales = ['es', 'en'], ?string $overrideDir = null): string
    {
        $views = new FilesystemLoader();
        if ($overrideDir !== null) {
            $views->addPath($overrideDir, self::DOMAIN);
        }
        $views->addPath(dirname(__DIR__, 3) . '/src/Resources/views', self::DOMAIN);
        $bridgeViews = dirname((string) new ReflectionClass(FormExtension::class)->getFileName(), 2) . '/Resources/views/Form';
        $views->addPath($bridgeViews);

        // Only bundle + Symfony templates: no components/admin/* or form/auth_theme.html.twig.
        $twig = new Environment(new ChainLoader([$views]), ['strict_variables' => true]);

        $translator = new Translator($uiLocale);
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource(
            'yaml',
            dirname(__DIR__, 3) . '/src/Resources/translations/' . self::DOMAIN . '.' . $uiLocale . '.yaml',
            $uiLocale,
            self::DOMAIN,
        );

        $engine = new TwigRendererEngine(['form_div_layout.html.twig'], $twig);
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            FormRenderer::class => static fn (): FormRenderer => new FormRenderer($engine),
        ]));
        $twig->addExtension(new FormExtension());
        $twig->addExtension(new TranslationExtension($translator));
        $twig->addExtension($this->extension());

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
            ->addTypes([
                new PageHeroBlockModalType($merger, new FormTypeMap()),
                new PageHeroBlockEditType($merger, new FormTypeMap()),
            ])
            ->getFormFactory();
        $form = $factory->create(PageHeroBlockModalType::class, $hero, ['action' => '/admin/page-blocks/hero/1']);

        return $twig->render('@NowoPageLayoutKitBundle/admin/_modal_form.html.twig', [
            'form'   => $form->createView(),
            'locale' => $uiLocale,
        ]);
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
