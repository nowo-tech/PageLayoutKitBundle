<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Form;

use Nowo\PageLayoutKitBundle\Entity\PageTextBlockTranslation;
use Nowo\PageLayoutKitBundle\Form\AbstractPageLayoutFormType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractPageLayoutFormType<PageTextBlockTranslation> */
final class PageTextBlockEditType extends AbstractPageLayoutFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function () use ($options): void {
            $this->addHiddenLocaleField();

            if ($options['include_meta']) {
                $this->addTextField('pageTitle', [
                    'required' => false,
                ]);
                $this->addTextareaField('pageDescription', [
                    'required' => false,
                ]);
            }

            $this->addTextField('title');
            $this->addCkeditor5Field('body', [
                'config' => 'simple',
                'theme' => 'auto',
            ]);
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'data_class' => PageTextBlockTranslation::class,
            'include_meta' => false,
        ]);
    }
}
