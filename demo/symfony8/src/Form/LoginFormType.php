<?php

declare(strict_types=1);

namespace App\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Demo login form (REQ-DEMO-015, REQ-TWIG-005): a FormKit form (profile `demo`); field names match form_login
 * (`_username`, `_password`, `_csrf_token`).
 *
 * @extends AbstractType<array{_username?: string, _password?: string}>
 */
#[FormKitConfig('demo')]
final class LoginFormType extends AbstractType
{
    use FormOptionsTrait;

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addText($builder, '_username', [
            'label'              => 'Username',
            'translation_domain' => false,
            'placeholder'        => false,
            'help'               => false,
            'attr'               => ['autocomplete' => 'username', 'autofocus' => true],
        ]);
        $this->addPassword($builder, '_password', [
            'label'              => 'Password',
            'translation_domain' => false,
            'placeholder'        => false,
            'help'               => false,
            'attr'               => ['autocomplete' => 'current-password'],
        ]);
        $this->addWithDefaults($builder, 'submit', SubmitType::class, [
            'label'              => 'Sign in',
            'translation_domain' => false,
            'attr'               => ['class' => 'nowo-ui-btn nowo-ui-btn--primary'],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method'          => 'POST',
            'csrf_protection' => true,
            'csrf_field_name' => '_csrf_token',
            'csrf_token_id'   => 'authenticate',
        ]);
    }

    /**
     * Empty prefix so field names match Symfony Security expectations.
     */
    public function getBlockPrefix(): string
    {
        return '';
    }
}
