<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Form;

use Nowo\PageLayoutKitBundle\Entity\PageHeroBlock;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractPageLayoutFormType<PageHeroBlock> */
final class PageHeroBlockModalType extends AbstractPageLayoutFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addTranslationsCollectionField(PageHeroBlockEditType::class);
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'data_class' => PageHeroBlock::class,
        ]);
    }
}
