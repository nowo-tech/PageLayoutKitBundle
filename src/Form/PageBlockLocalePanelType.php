<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Form;

use Nowo\PageLayoutKitBundle\Form\AbstractPageLayoutFormType;
use Override;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractPageLayoutFormType<PageBlockLocalePanelData> */
final class PageBlockLocalePanelType extends AbstractPageLayoutFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function (): void {
            $this->addHiddenLocaleField();
            $this->addTextField('title');
            $this->addTextareaField('items', [
                'required' => false,
                'attr' => ['rows' => 10],
            ]);
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'data_class' => PageBlockLocalePanelData::class,
        ]);
    }
}
