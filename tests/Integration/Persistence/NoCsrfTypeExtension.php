<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Integration\Persistence;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Test-only: the kernel tests POST the admin forms without a browser session, so the CSRF field is switched off here
 * (the bundle itself requires CSRF to be enabled, see CsrfProtectionPass).
 */
final class NoCsrfTypeExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('csrf_protection', false);
    }
}
