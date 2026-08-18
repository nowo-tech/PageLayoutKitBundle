<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Form;

use Nowo\PageLayoutKitBundle\Entity\PageTextBlock;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractPageLayoutFormType<PageTextBlock> */
final class PageTextBlockModalType extends AbstractPageLayoutFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function () use ($options): void {
            $this->addTranslationsCollectionField(PageTextBlockEditType::class, [
                'entry_options' => [
                    'include_meta' => (bool) $options['include_meta'],
                ],
            ]);
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'data_class'   => PageTextBlock::class,
            'include_meta' => false,
        ]);
    }
}
