<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Service;

use App\Entity\Site\PageContent;
use App\Tests\Unit\Support\LocaleTestSupport;
use App\Tests\Unit\Support\RepositoryTestSupport;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;
use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use Nowo\PageLayoutKitBundle\Service\PageBlockMigrator;
use PHPUnit\Framework\TestCase;

use function count;

final class PageBlockMigratorTest extends TestCase
{
    private int $nextId = 1;

    /** @var list<object> */
    private array $persisted = [];

    protected function setUp(): void
    {
        LocaleTestSupport::bindDefaults();
        $this->nextId    = 1;
        $this->persisted = [];
    }

    public function testIsEmptyWhenNoLayoutEntries(): void
    {
        $migrator = $this->migrator(
            layoutEntries: [],
            homeContent: $this->homeContent(),
            contactContent: $this->contactContent(),
        );

        self::assertTrue($migrator->isEmpty());
    }

    public function testIsEmptyFalseWhenLayoutExists(): void
    {
        $entry = (new PageLayoutEntry())
            ->setPageKey('home')
            ->setBlockType(PageBlockType::Hero)
            ->setBlockId(1)
            ->setPosition(0);

        $migrator = $this->migrator(
            layoutEntries: [$entry],
            homeContent: $this->homeContent(),
            contactContent: $this->contactContent(),
        );

        self::assertFalse($migrator->isEmpty());
    }

    public function testMigrateReturnsFalseWhenNotEmptyAndNotForced(): void
    {
        $entry = (new PageLayoutEntry())
            ->setPageKey('home')
            ->setBlockType(PageBlockType::Hero)
            ->setBlockId(1)
            ->setPosition(0);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $migrator = new PageBlockMigrator(
            $entityManager,
            RepositoryTestSupport::pageLayoutEntryRepository(['home' => [$entry]]),
            RepositoryTestSupport::pageContentRepository([]),
        );

        self::assertFalse($migrator->migrate(false));
    }

    public function testMigrateWhenEmptyPersistsHomeAndContactBlocks(): void
    {
        $entityManager = $this->entityManagerMock();
        $migrator      = new PageBlockMigrator(
            $entityManager,
            RepositoryTestSupport::pageLayoutEntryRepository([]),
            RepositoryTestSupport::pageContentRepository($this->pageContentMap()),
        );

        self::assertTrue($migrator->migrate(false));

        $layoutEntries = array_values(array_filter(
            $this->persisted,
            static fn (object $entity): bool => $entity instanceof PageLayoutEntry,
        ));
        self::assertCount(14, $layoutEntries);
        self::assertSame('home', $layoutEntries[0]->getPageKey());
        self::assertSame(PageBlockType::Hero, $layoutEntries[0]->getBlockType());
        self::assertSame('contact', $layoutEntries[9]->getPageKey());
        self::assertSame(PageBlockType::Compare, $layoutEntries[12]->getBlockType());
    }

    public function testMigrateForceClearsExistingBeforeMigrating(): void
    {
        $entityManager = $this->entityManagerMock(withDeleteQueries: 7);

        $existing = (new PageLayoutEntry())
            ->setPageKey('home')
            ->setBlockType(PageBlockType::Text)
            ->setBlockId(99)
            ->setPosition(0);

        $migrator = new PageBlockMigrator(
            $entityManager,
            RepositoryTestSupport::pageLayoutEntryRepository(['home' => [$existing]]),
            RepositoryTestSupport::pageContentRepository($this->pageContentMap()),
        );

        self::assertTrue($migrator->migrate(true));
        self::assertNotEmpty(array_filter(
            $this->persisted,
            static fn (object $entity): bool => $entity instanceof PageLayoutEntry,
        ));
    }

    public function testMigrateUsesDefaultLocaleFallbackForMissingTranslations(): void
    {
        $homeEs = (new PageContent())
            ->setPageKey('home')
            ->setLocale('es')
            ->setData(['hero_title' => 'Titulo ES', 'value_title' => 'Valor ES']);
        $contactEs = (new PageContent())
            ->setPageKey('contact')
            ->setLocale('es')
            ->setData(['h1' => 'Contacto ES', 'expect_items' => ['Uno']]);

        $entityManager = $this->entityManagerMock();
        $migrator      = new PageBlockMigrator(
            $entityManager,
            RepositoryTestSupport::pageLayoutEntryRepository([]),
            RepositoryTestSupport::pageContentRepository([
                'home:es'    => $homeEs,
                'contact:es' => $contactEs,
            ]),
        );

        self::assertTrue($migrator->migrate(false));
        self::assertGreaterThanOrEqual(10, count($this->persisted));
    }

    /** @param list<PageLayoutEntry> $layoutEntries */
    private function migrator(array $layoutEntries, PageContent $homeContent, PageContent $contactContent): PageBlockMigrator
    {
        return new PageBlockMigrator(
            $this->entityManagerMock(),
            RepositoryTestSupport::pageLayoutEntryRepository(['home' => $layoutEntries]),
            RepositoryTestSupport::pageContentRepository([
                'home:es'    => $homeContent,
                'home:en'    => $homeContent,
                'contact:es' => $contactContent,
                'contact:en' => $contactContent,
            ]),
        );
    }

    /** @return array<string, PageContent> */
    private function pageContentMap(): array
    {
        $home    = $this->homeContent();
        $contact = $this->contactContent();

        return [
            'home:es'    => $home,
            'home:en'    => $home,
            'contact:es' => $contact,
            'contact:en' => $contact,
        ];
    }

    private function homeContent(): PageContent
    {
        return (new PageContent())
            ->setPageKey('home')
            ->setLocale('es')
            ->setData([
                'page_title'         => 'Inicio',
                'page_description'   => 'Meta inicio',
                'hero_eyebrow'       => 'Hola',
                'hero_title'         => 'Hero',
                'hero_subtitle'      => 'Sub',
                'hero_cta_primary'   => 'Ir',
                'hero_cta_secondary' => 'Mas',
                'problem_title'      => 'Problema',
                'problem_text'       => 'Texto problema',
                'value_title'        => 'Valor',
                'value_1_title'      => 'V1',
                'value_1_text'       => 'VT1',
                'value_2_title'      => 'V2',
                'value_2_text'       => 'VT2',
                'value_3_title'      => 'V3',
                'value_3_text'       => 'VT3',
                'value_4_title'      => 'V4',
                'value_4_text'       => 'VT4',
                'pain_title'         => 'Dolor',
                'pain_1_title'       => 'P1',
                'pain_1_text'        => 'PT1',
                'pain_2_title'       => 'P2',
                'pain_2_text'        => 'PT2',
                'pain_3_title'       => 'P3',
                'pain_3_text'        => 'PT3',
                'detect_title'       => 'Detectar',
                'detect_items'       => ['Uno', 'Dos'],
                'services_title'     => 'Servicios',
                'services_text'      => 'Texto servicios',
                'profile_title'      => 'Perfil',
                'profile_text'       => 'Texto perfil',
                'process_title'      => 'Proceso',
                'process_items'      => ['Paso 1', 'Paso 2'],
                'cta_title'          => 'CTA',
                'cta_text'           => 'Texto CTA',
            ]);
    }

    private function contactContent(): PageContent
    {
        return (new PageContent())
            ->setPageKey('contact')
            ->setLocale('es')
            ->setData([
                'page_title'       => 'Contacto',
                'page_description' => 'Meta contacto',
                'h1'               => 'Hablemos',
                'intro'            => 'Intro contacto',
                'expect_title'     => 'Que esperar',
                'expect_text'      => 'Texto expect',
                'expect_items'     => ['Item A', 'Item B'],
                'before_label'     => 'Antes',
                'before_text'      => 'Texto antes',
                'after_label'      => 'Despues',
                'after_text'       => 'Texto despues',
                'form_submit'      => 'Enviar',
                'form_note'        => 'Nota form',
            ]);
    }

    private function entityManagerMock(int $withDeleteQueries = 0): EntityManagerInterface
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;

            if (method_exists($entity, 'getId') && $entity->getId() === null) {
                RepositoryTestSupport::assignEntityId($entity, $this->nextId++);
            }
        });
        $entityManager->method('flush')->willReturnCallback(function (): void {
            foreach ($this->persisted as $entity) {
                if (method_exists($entity, 'getId') && $entity->getId() === null) {
                    RepositoryTestSupport::assignEntityId($entity, $this->nextId++);
                }
            }
        });

        if ($withDeleteQueries > 0) {
            $deleteQuery = $this->createMock(Query::class);
            $deleteQuery->expects(self::exactly($withDeleteQueries))->method('execute');
            $entityManager->expects(self::exactly($withDeleteQueries))
                ->method('createQuery')
                ->willReturn($deleteQuery);
        }

        return $entityManager;
    }
}
