<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Override;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function array_key_exists;

/**
 * Page layout kit product forms — FormKit profile page_layout_kit.
 *
 * @template TData
 *
 * @extends FormKitAbstractType<mixed>
 */
#[FormKitConfig('page_layout_kit')]
abstract class AbstractPageLayoutFormType extends FormKitAbstractType
{
    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'translation_domain' => 'form',
        ]);
    }

    /** @param array<string, mixed> $options */
    protected function addCkeditor5Field(string $name, array $options = []): void
    {
        $ckeditor = 'Nowo\\Ckeditor5EditorBundle\\Form\\Ckeditor5EditorType';
        $type     = class_exists($ckeditor) ? 'ckeditor5' : 'textarea';
        if ($type === 'textarea') {
            $options['attr'] = array_merge(['rows' => 8], $options['attr'] ?? []);
            unset($options['config'], $options['theme'], $options['min_height']);
        }
        $this->addNamedField($name, $type, $options);
    }

    /**
     * @param class-string $entryType
     * @param array<string, mixed> $options
     */
    protected function addTranslationsCollectionField(string $entryType, array $options = []): void
    {
        $this->addWithDefaults($this->boundBuilder(), 'translations', CollectionType::class, [
            'entry_type'   => $entryType,
            'allow_add'    => false,
            'allow_delete' => false,
            ...$options,
        ]);
    }

    /** @param array<string, mixed> $options */
    protected function addHiddenLocaleField(array $options = []): void
    {
        $this->addNamedField('locale', 'hidden', [
            'label' => false,
            'help'  => false,
            ...$options,
        ]);
    }

    /**
     * @param FormBuilderInterface<TData> $builder
     * @param array<string, mixed> $options
     */
    protected function addWithDefaults(
        FormBuilderInterface $builder,
        string $name,
        string $type,
        array $options = [],
    ): void {
        if (!array_key_exists('placeholder', $options)) {
            $options['placeholder'] = false;
        }

        $this->withBuilder($builder, function () use ($name, $type, $options): void {
            $alias = str_contains($type, '\\')
                ? match ($type) {
                    HiddenType::class     => 'hidden',
                    TextareaType::class   => 'textarea',
                    CollectionType::class => 'collection',
                    default               => $type,
                }
            : $type;
            $this->addNamedField($name, $alias, $options);
        });
    }
}
