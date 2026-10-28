<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\DependencyInjection\Compiler;

use LogicException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Fails closed when the layout admin would run without form CSRF protection (REQ-SEC-005).
 *
 * Block reorder and block edit are validated through Symfony forms whose types define their own CSRF options
 * (`PageLayoutReorderType`, block edit forms). A host that disables `framework.form.csrf_protection` (or has no CSRF
 * token manager) would silently accept forged POSTs, so the container refuses to compile instead.
 */
final class CsrfProtectionPass implements CompilerPassInterface
{
    /**
     * Throws when the bundle is loaded and form CSRF protection or the CSRF token manager is unavailable.
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('nowo_page_layout_kit.security.access_roles')) {
            return;
        }

        $formCsrfDisabled = $container->hasParameter('form.type_extension.csrf.enabled')
            && $container->getParameter('form.type_extension.csrf.enabled') === false;

        if (
            !$container->has('security.csrf.token_manager')
            || !$container->hasDefinition('form.type_extension.csrf')
            || $formCsrfDisabled
        ) {
            throw new LogicException('NowoPageLayoutKitBundle: the layout admin requires CSRF protection. Enable framework.csrf_protection and framework.form.csrf_protection (composer require symfony/security-csrf).');
        }
    }
}
