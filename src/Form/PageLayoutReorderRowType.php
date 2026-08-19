<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Form;

use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Single layout entry row (hidden id + position integer).
 */
final class PageLayoutReorderRowType extends AbstractPageLayoutFormType
{
    /** @param FormBuilderInterface<mixed> $builder */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addNamedField('id', 'hidden', [
                'label' => false,
            ]);
            $this->addNamedField('position', 'integer', [
                'label' => false,
                'attr'  => [
                    'class' => 'form-control nowo-ui-input',
                    'min'   => 0,
                    'style' => 'width:5rem',
                ],
            ]);
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
            'data_class' => PageLayoutReorderRowData::class,
        ]);
    }
}
