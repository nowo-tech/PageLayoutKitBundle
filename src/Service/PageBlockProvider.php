<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Service;

use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use Nowo\PageLayoutKitBundle\Legacy\LegacyPageContentProviderInterface;
use Nowo\PageLayoutKitBundle\Locale\PageLocales;
use Nowo\PageLayoutKitBundle\Repository\PageBlockSqlRepository;
use Nowo\PageLayoutKitBundle\Repository\PageLayoutEntryRepository;
use Nowo\PageLayoutKitBundle\Security\PageLayoutProtection;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;

use function is_array;
use function is_string;
use function sprintf;

/**
 * Resolves ordered page blocks for configured page keys, with optional legacy JSON fallback.
 */
final class PageBlockProvider implements ResetInterface
{
    /** @var array<string, list<PageBlockView>> */
    private array $layoutCache = [];

    public function __construct(
        private readonly PageLayoutEntryRepository $pageLayoutEntryRepository,
        private readonly PageBlockSqlRepository $pageBlockSqlRepository,
        private readonly RequestStack $requestStack,
        private readonly PageLocales $pageLocales,
        private readonly PageLayoutProtection $protection,
        private readonly ?LegacyPageContentProviderInterface $legacyContentProvider = null,
    ) {
    }

    public function reset(): void
    {
        $this->layoutCache = [];
    }

    /** @return list<PageBlockView> */
    public function getLayout(string $pageKey, ?string $locale = null): array
    {
        $locale ??= $this->currentLocale();
        $cacheKey = $pageKey . '|' . $locale;

        if (isset($this->layoutCache[$cacheKey])) {
            return $this->layoutCache[$cacheKey];
        }

        $entries = $this->pageLayoutEntryRepository->findEnabledByPageKey($pageKey);

        if ($entries === []) {
            return $this->layoutCache[$cacheKey] = $this->legacyLayout($pageKey, $locale);
        }

        $dataByKey = $this->pageBlockSqlRepository->loadDataForEntries($entries, $locale);
        $views     = [];

        foreach ($entries as $entry) {
            $dataKey = $entry->getBlockType()->value . ':' . $entry->getBlockId();
            $data    = $dataByKey[$dataKey] ?? null;

            if (!is_array($data)) {
                continue;
            }

            $data = $this->sanitizeBlockData($data);

            $sectionKey = $data['sectionKey'] ?? null;

            if (!is_string($sectionKey) || $sectionKey === '') {
                $sectionKey = null;
            }

            $views[] = new PageBlockView(
                layoutId: (int) $entry->getId(),
                pageKey: $entry->getPageKey(),
                type: $entry->getBlockType(),
                blockId: (int) $entry->getBlockId(),
                sectionKey: $sectionKey,
                data: $data,
            );
        }

        return $this->layoutCache[$cacheKey] = $views;
    }

    /** @return array{title: string, description: string} */
    public function pageMeta(string $pageKey, ?string $locale = null): array
    {
        $locale ??= $this->currentLocale();

        foreach ($this->getLayout($pageKey, $locale) as $pageBlockView) {
            if ($pageBlockView->type === PageBlockType::Hero) {
                return [
                    'title'       => (string) ($pageBlockView->data['pageTitle'] ?? ''),
                    'description' => (string) ($pageBlockView->data['pageDescription'] ?? ''),
                ];
            }

            if ($pageBlockView->type === PageBlockType::Text && $pageBlockView->sectionKey === 'contact_header') {
                return [
                    'title'       => (string) ($pageBlockView->data['pageTitle'] ?? $pageBlockView->data['title'] ?? ''),
                    'description' => (string) ($pageBlockView->data['pageDescription'] ?? $pageBlockView->data['body'] ?? ''),
                ];
            }
        }

        $legacy = $this->legacyContent($pageKey, $locale);

        return [
            'title'       => (string) ($legacy['page_title'] ?? ''),
            'description' => (string) ($legacy['page_description'] ?? ''),
        ];
    }

    public function hasLayout(string $pageKey): bool
    {
        return $this->pageLayoutEntryRepository->findEnabledByPageKey($pageKey) !== [];
    }

    /** @return list<PageBlockView> */
    private function legacyLayout(string $pageKey, string $locale): array
    {
        $content = $this->legacyContent($pageKey, $locale);

        $views = match ($pageKey) {
            'home'    => $this->legacyHomeLayout($content),
            'contact' => $this->legacyContactLayout($content),
            default   => [],
        };

        return array_map(
            fn (PageBlockView $view): PageBlockView => new PageBlockView(
                layoutId: $view->layoutId,
                pageKey: $view->pageKey,
                type: $view->type,
                blockId: $view->blockId,
                sectionKey: $view->sectionKey,
                data: $this->sanitizeBlockData($view->data),
            ),
            $views,
        );
    }

    /** @param array<string, mixed> $content
     * @return list<PageBlockView>
     */
    private function legacyHomeLayout(array $content): array
    {
        return [
            new PageBlockView(0, 'home', PageBlockType::Hero, 0, null, [
                'pageTitle'       => $content['page_title'] ?? '',
                'pageDescription' => $content['page_description'] ?? '',
                'eyebrow'         => $content['hero_eyebrow'] ?? '',
                'title'           => $content['hero_title'] ?? '',
                'subtitle'        => $content['hero_subtitle'] ?? '',
                'ctaPrimary'      => $content['hero_cta_primary'] ?? '',
                'ctaSecondary'    => $content['hero_cta_secondary'] ?? '',
            ]),
            new PageBlockView(0, 'home', PageBlockType::Text, 0, 'problem', [
                'title' => $content['problem_title'] ?? '',
                'body'  => $content['problem_text'] ?? '',
            ]),
            new PageBlockView(0, 'home', PageBlockType::Cards, 0, 'value', [
                'title' => $content['value_title'] ?? '',
                'items' => $this->legacyCardItems($content, 'value', 4),
            ]),
            new PageBlockView(0, 'home', PageBlockType::Cards, 0, 'pain', [
                'title' => $content['pain_title'] ?? '',
                'items' => $this->legacyCardItems($content, 'pain', 3),
            ]),
            new PageBlockView(0, 'home', PageBlockType::List, 0, 'detect', [
                'title' => $content['detect_title'] ?? '',
                'items' => array_map(
                    static fn (string $text): array => ['text' => $text],
                    $content['detect_items'] ?? [],
                ),
            ]),
            new PageBlockView(0, 'home', PageBlockType::Text, 0, 'services', [
                'title' => $content['services_title'] ?? '',
                'body'  => $content['services_text'] ?? '',
            ]),
            new PageBlockView(0, 'home', PageBlockType::Text, 0, 'profile', [
                'title' => $content['profile_title'] ?? '',
                'body'  => $content['profile_text'] ?? '',
            ]),
            new PageBlockView(0, 'home', PageBlockType::List, 0, 'process', [
                'title' => $content['process_title'] ?? '',
                'items' => array_map(
                    static fn (string $text): array => ['text' => $text],
                    $content['process_items'] ?? [],
                ),
            ]),
            new PageBlockView(0, 'home', PageBlockType::Cta, 0, null, [
                'title' => $content['cta_title'] ?? '',
                'body'  => $content['cta_text'] ?? '',
            ]),
        ];
    }

    /** @param array<string, mixed> $content
     * @return list<PageBlockView>
     */
    private function legacyContactLayout(array $content): array
    {
        return [
            new PageBlockView(0, 'contact', PageBlockType::Text, 0, 'contact_header', [
                'pageTitle'       => $content['page_title'] ?? '',
                'pageDescription' => $content['page_description'] ?? '',
                'title'           => $content['h1'] ?? '',
                'body'            => $content['intro'] ?? '',
            ]),
            new PageBlockView(0, 'contact', PageBlockType::Text, 0, 'expect', [
                'title' => $content['expect_title'] ?? '',
                'body'  => $content['expect_text'] ?? '',
            ]),
            new PageBlockView(0, 'contact', PageBlockType::List, 0, 'expect', [
                'title' => '',
                'items' => array_map(
                    static fn (string $text): array => ['text' => $text],
                    $content['expect_items'] ?? [],
                ),
            ]),
            new PageBlockView(0, 'contact', PageBlockType::Compare, 0, null, [
                'beforeLabel' => $content['before_label'] ?? '',
                'beforeText'  => $content['before_text'] ?? '',
                'afterLabel'  => $content['after_label'] ?? '',
                'afterText'   => $content['after_text'] ?? '',
            ]),
            new PageBlockView(0, 'contact', PageBlockType::Cta, 0, 'contact_form', [
                'title' => $content['form_submit'] ?? '',
                'body'  => $content['form_note'] ?? '',
            ]),
        ];
    }

    /** @param array<string, mixed> $content
     * @return list<array{title: string, body: string}>
     */
    private function legacyCardItems(array $content, string $prefix, int $count): array
    {
        $items = [];

        for ($i = 1; $i <= $count; ++$i) {
            $items[] = [
                'title' => $content[sprintf('%s_%d_title', $prefix, $i)] ?? '',
                'body'  => $content[sprintf('%s_%d_text', $prefix, $i)] ?? '',
            ];
        }

        return $items;
    }

    /** @return array<string, mixed> */
    private function legacyContent(string $pageKey, string $locale): array
    {
        if (!$this->legacyContentProvider instanceof LegacyPageContentProviderInterface) {
            return [];
        }

        $content = $this->legacyContentProvider->contentForPage($pageKey, $locale);
        if ($content !== []) {
            return $content;
        }

        $fallback = $this->legacyContentProvider->defaultLocale();
        if ($fallback === $locale) {
            return [];
        }

        return $this->legacyContentProvider->contentForPage($pageKey, $fallback);
    }

    private function currentLocale(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request?->getLocale() ?? $this->pageLocales->getDefault();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function sanitizeBlockData(array $data): array
    {
        $sanitizer = $this->protection->htmlSanitizer();

        if (isset($data['body']) && is_string($data['body']) && $data['body'] !== '') {
            $data['body'] = $sanitizer->sanitize($data['body']);
        }

        if (isset($data['beforeText']) && is_string($data['beforeText']) && $data['beforeText'] !== '') {
            $data['beforeText'] = $sanitizer->sanitize($data['beforeText']);
        }

        if (isset($data['afterText']) && is_string($data['afterText']) && $data['afterText'] !== '') {
            $data['afterText'] = $sanitizer->sanitize($data['afterText']);
        }

        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $index => $item) {
                if (!is_array($item) || !isset($item['body']) || !is_string($item['body']) || $item['body'] === '') {
                    continue;
                }

                $data['items'][$index]['body'] = $sanitizer->sanitize($item['body']);
            }
        }

        return $data;
    }
}
