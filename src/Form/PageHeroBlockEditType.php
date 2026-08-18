<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Form;

use Nowo\PageLayoutKitBundle\Entity\PageHeroBlockTranslation;
use Nowo\PageLayoutKitBundle\Form\AbstractPageLayoutFormType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractPageLayoutFormType<PageHeroBlockTranslation> */
final class PageHeroBlockEditType extends AbstractPageLayoutFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addHiddenLocaleField();
            $this->addTextField('pageTitle');
            $this->addTextareaField('pageDescription');
            $this->addTextField('eyebrow');
            $this->addTextField('title');
            $this->addTextareaField('subtitle');
            $this->addTextField('ctaPrimary');
            $this->addTextField('ctaSecondary');
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'data_class' => PageHeroBlockTranslation::class,
        ]);
    }
}
