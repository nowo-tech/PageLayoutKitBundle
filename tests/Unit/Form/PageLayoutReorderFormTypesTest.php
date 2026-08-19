<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Form;

use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;
use Nowo\FormKitBundle\Form\FormTypeMap;
use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;
use Nowo\PageLayoutKitBundle\Enum\PageBlockType;
use Nowo\PageLayoutKitBundle\Form\PageLayoutReorderData;
use Nowo\PageLayoutKitBundle\Form\PageLayoutReorderRowData;
use Nowo\PageLayoutKitBundle\Form\PageLayoutReorderRowType;
use Nowo\PageLayoutKitBundle\Form\PageLayoutReorderType;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PageLayoutReorderFormTypesTest extends TestCase
{
    public function testReorderTypeDefaults(): void
    {
        $options = $this->resolveOptions($this->createType(PageLayoutReorderType::class));

        self::assertSame(PageLayoutReorderData::class, $options['data_class']);
        self::assertSame('page_layout_reorder', $options['csrf_token_id']);
    }

    public function testReorderDataAppliesPositionsToEntries(): void
    {
        $first                   = $this->createEntry(10, 1);
        $second                  = $this->createEntry(11, 2);
        $data                    = PageLayoutReorderData::fromEntries([$first, $second]);
        $data->rows[0]->position = 9;

        $data->applyToEntries([$first, $second]);

        self::assertSame(9, $first->getPosition());
        self::assertSame(2, $second->getPosition());
    }

    public function testReorderDataSkipsUnknownEntryIds(): void
    {
        $known                   = $this->createEntry(10, 1);
        $data                    = PageLayoutReorderData::fromEntries([$known]);
        $data->rows[0]->id       = 999;
        $data->rows[0]->position = 4;

        $data->applyToEntries([$known]);

        self::assertSame(1, $known->getPosition());
    }

    public function testReorderRowTypeDefaults(): void
    {
        $options = $this->resolveOptions($this->createType(PageLayoutReorderRowType::class));

        self::assertSame(PageLayoutReorderRowData::class, $options['data_class']);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function resolveOptions(object $type, array $input = []): array
    {
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        return $resolver->resolve($input);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $typeClass
     *
     * @return T
     */
    private function createType(string $typeClass): object
    {
        return new $typeClass(
            new FormOptionsMerger([
                'page_layout_kit' => [
                    'translation_domain' => 'form',
                    'defaults'           => [
                        'attr'     => [],
                        'row_attr' => [],
                    ],
                    'field_types' => [],
                ],
            ], 'page_layout_kit', new ConstraintDefinitionFactory()),
            new FormTypeMap(),
        );
    }

    private function createEntry(int $id, int $position): PageLayoutEntry
    {
        $entry = (new PageLayoutEntry())
            ->setPageKey('home')
            ->setBlockType(PageBlockType::Hero)
            ->setBlockId($id)
            ->setPosition($position);

        $property = new ReflectionProperty(PageLayoutEntry::class, 'id');
        $property->setValue($entry, $id);

        return $entry;
    }
}
