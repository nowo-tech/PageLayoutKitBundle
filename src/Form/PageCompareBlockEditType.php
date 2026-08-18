<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Form;

use Nowo\PageLayoutKitBundle\Entity\PageCompareBlockTranslation;
use Nowo\PageLayoutKitBundle\Form\AbstractPageLayoutFormType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractPageLayoutFormType<PageCompareBlockTranslation> */
final class PageCompareBlockEditType extends AbstractPageLayoutFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addHiddenLocaleField();
            $this->addTextField('beforeLabel');
            $this->addCkeditor5Field('beforeText', [
                'config' => 'simple',
                'theme' => 'auto',
                'min_height' => '160px',
            ]);
            $this->addTextField('afterLabel');
            $this->addCkeditor5Field('afterText', [
                'config' => 'simple',
                'theme' => 'auto',
                'min_height' => '160px',
            ]);
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'data_class' => PageCompareBlockTranslation::class,
        ]);
    }
}
