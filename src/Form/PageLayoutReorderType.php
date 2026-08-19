<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Form;

use Override;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * CSRF-protected reorder form for layout admin (REQ-TWIG-005).
 */
final class PageLayoutReorderType extends AbstractPageLayoutFormType
{
    /** @param FormBuilderInterface<mixed> $builder */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addWithDefaults($builder, 'rows', CollectionType::class, [
            'entry_type'   => PageLayoutReorderRowType::class,
            'allow_add'    => false,
            'allow_delete' => false,
            'label'        => false,
        ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class'         => PageLayoutReorderData::class,
            'csrf_token_id'      => 'page_layout_reorder',
            'translation_domain' => 'NowoPageLayoutKitBundle',
        ]);
    }
}
