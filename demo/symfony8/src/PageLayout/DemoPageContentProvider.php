<?php

declare(strict_types=1);

namespace App\PageLayout;

use Nowo\PageLayoutKitBundle\Legacy\LegacyPageContentProviderInterface;

/**
 * Demo seed content: `nowo:page-layout:migrate --if-empty` (run by `make up`) turns it into typed
 * page blocks, so the layout admin and the block edit modal have data to work with.
 */
final class DemoPageContentProvider implements LegacyPageContentProviderInterface
{
    private const array CONTENT = [
        'home' => [
            'en' => [
                'page_title' => 'Home',
                'page_description' => 'Composable page blocks edited from the admin.',
                'hero_eyebrow' => 'PageLayoutKitBundle',
                'hero_title' => 'Pages built from editable blocks',
                'hero_subtitle' => 'Reorder hero, text, cards, lists and calls to action per page, in every locale.',
                'hero_cta_primary' => 'Get started',
                'hero_cta_secondary' => 'Contact us',
                'problem_title' => 'Static templates age fast',
                'problem_text' => '<p>Marketing copy changes every week, but it is hard-coded in Twig.</p>',
                'value_title' => 'What you get',
                'value_1_title' => 'Typed blocks',
                'value_1_text' => 'Hero, text, cards, list, compare and CTA entities.',
                'value_2_title' => 'Translations',
                'value_2_text' => 'One tab per configured locale.',
                'value_3_title' => 'Ordering',
                'value_3_text' => 'Drag-free reorder form per page.',
                'value_4_title' => 'Inline editing',
                'value_4_text' => 'Edit modals for authorised users.',
                'pain_title' => 'Common pains',
                'pain_1_title' => 'Deploys for copy',
                'pain_1_text' => 'Every text change needs a release.',
                'pain_2_title' => 'Missing locales',
                'pain_2_text' => 'Translations drift between pages.',
                'pain_3_title' => 'Rigid order',
                'pain_3_text' => 'Sections cannot be rearranged.',
                'detect_title' => 'Signs you need it',
                'detect_items' => ['Copy lives in templates', 'Editors ask developers for changes', 'Pages differ per locale'],
                'services_title' => 'How it works',
                'services_text' => '<p>Blocks are Doctrine entities; a layout entry places each block on a page.</p>',
                'profile_title' => 'Who it is for',
                'profile_text' => '<p>Symfony sites whose landing pages are edited by non-developers.</p>',
                'process_title' => 'Process',
                'process_items' => ['Install the bundle', 'Migrate or create blocks', 'Edit and reorder from the admin'],
                'cta_title' => 'Ready to try it?',
                'cta_text' => '<p>Log in as admin/admin and open the layout admin.</p>',
            ],
            'es' => [
                'page_title' => 'Inicio',
                'page_description' => 'Bloques de página componibles editados desde el admin.',
                'hero_eyebrow' => 'PageLayoutKitBundle',
                'hero_title' => 'Páginas hechas con bloques editables',
                'hero_subtitle' => 'Reordena hero, texto, tarjetas, listas y llamadas a la acción por página, en cada idioma.',
                'hero_cta_primary' => 'Empezar',
                'hero_cta_secondary' => 'Contacto',
                'problem_title' => 'Las plantillas estáticas envejecen rápido',
                'problem_text' => '<p>El texto de marketing cambia cada semana, pero está fijo en Twig.</p>',
                'value_title' => 'Qué obtienes',
                'value_1_title' => 'Bloques tipados',
                'value_1_text' => 'Entidades hero, texto, tarjetas, lista, comparación y CTA.',
                'value_2_title' => 'Traducciones',
                'value_2_text' => 'Una pestaña por idioma configurado.',
                'value_3_title' => 'Orden',
                'value_3_text' => 'Formulario de reordenación por página.',
                'value_4_title' => 'Edición en línea',
                'value_4_text' => 'Modales de edición para usuarios autorizados.',
                'pain_title' => 'Problemas habituales',
                'pain_1_title' => 'Despliegues por textos',
                'pain_1_text' => 'Cada cambio de texto necesita una release.',
                'pain_2_title' => 'Idiomas incompletos',
                'pain_2_text' => 'Las traducciones divergen entre páginas.',
                'pain_3_title' => 'Orden rígido',
                'pain_3_text' => 'Las secciones no se pueden reordenar.',
                'detect_title' => 'Señales de que lo necesitas',
                'detect_items' => ['El texto vive en las plantillas', 'Los editores piden cambios a desarrollo', 'Las páginas difieren por idioma'],
                'services_title' => 'Cómo funciona',
                'services_text' => '<p>Los bloques son entidades Doctrine; una entrada de layout coloca cada bloque en una página.</p>',
                'profile_title' => 'Para quién es',
                'profile_text' => '<p>Sitios Symfony cuyas landings editan personas que no programan.</p>',
                'process_title' => 'Proceso',
                'process_items' => ['Instala el bundle', 'Migra o crea bloques', 'Edita y reordena desde el admin'],
                'cta_title' => '¿Listo para probarlo?',
                'cta_text' => '<p>Entra como admin/admin y abre el admin de layout.</p>',
            ],
        ],
        'contact' => [
            'en' => [
                'page_title' => 'Contact',
                'page_description' => 'Contact page built from page blocks.',
                'h1' => 'Talk to us',
                'intro' => '<p>Tell us about your project; we answer within two working days.</p>',
                'expect_title' => 'What to expect',
                'expect_text' => '<p>A short call to understand your pages and editors.</p>',
                'expect_items' => ['A reply within 48 hours', 'No commitment', 'A written summary'],
                'before_label' => 'Before',
                'before_text' => 'Copy changes wait for the next deploy.',
                'after_label' => 'After',
                'after_text' => 'Editors update blocks themselves.',
                'form_submit' => 'Send message',
                'form_note' => '<p>We only use your details to answer you.</p>',
            ],
            'es' => [
                'page_title' => 'Contacto',
                'page_description' => 'Página de contacto hecha con bloques.',
                'h1' => 'Hablemos',
                'intro' => '<p>Cuéntanos tu proyecto; respondemos en dos días laborables.</p>',
                'expect_title' => 'Qué esperar',
                'expect_text' => '<p>Una llamada breve para entender tus páginas y editores.</p>',
                'expect_items' => ['Respuesta en 48 horas', 'Sin compromiso', 'Un resumen por escrito'],
                'before_label' => 'Antes',
                'before_text' => 'Los cambios de texto esperan al siguiente despliegue.',
                'after_label' => 'Después',
                'after_text' => 'Los editores actualizan los bloques ellos mismos.',
                'form_submit' => 'Enviar mensaje',
                'form_note' => '<p>Solo usamos tus datos para responderte.</p>',
            ],
        ],
    ];

    public function contentForPage(string $pageKey, string $locale): array
    {
        return self::CONTENT[$pageKey][$locale] ?? [];
    }

    public function defaultLocale(): string
    {
        return 'en';
    }
}
