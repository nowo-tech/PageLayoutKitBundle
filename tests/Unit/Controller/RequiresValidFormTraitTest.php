<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\Controller;

use Nowo\PageLayoutKitBundle\Controller\RequiresValidFormTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class RequiresValidFormTraitTest extends TestCase
{
    public function testRequireValidFormAcceptsSubmittedValidForms(): void
    {
        $form = $this->createConfiguredMock(FormInterface::class, [
            'isSubmitted' => true,
            'isValid'     => true,
        ]);

        $controller = new class {
            use RequiresValidFormTrait;

            public function assertValid(FormInterface $form): void
            {
                $this->requireValidForm($form);
            }

            private function createAccessDeniedException(string $message): AccessDeniedHttpException
            {
                return new AccessDeniedHttpException($message);
            }
        };

        $controller->assertValid($form);
        self::assertTrue(true);
    }

    public function testRequireValidFormRejectsInvalidSubmission(): void
    {
        $form = $this->createConfiguredMock(FormInterface::class, [
            'isSubmitted' => true,
            'isValid'     => false,
        ]);

        $controller = new class {
            use RequiresValidFormTrait;

            public function assertValid(FormInterface $form): void
            {
                $this->requireValidForm($form, 'Custom message');
            }

            private function createAccessDeniedException(string $message): AccessDeniedHttpException
            {
                return new AccessDeniedHttpException($message);
            }
        };

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Custom message');

        $controller->assertValid($form);
    }

    public function testRequireValidCsrfFormUsesTheCsrfMessage(): void
    {
        $form = $this->createConfiguredMock(FormInterface::class, [
            'isSubmitted' => false,
            'isValid'     => false,
        ]);

        $controller = new class {
            use RequiresValidFormTrait;

            public function assertCsrf(FormInterface $form): void
            {
                $this->requireValidCsrfForm($form);
            }

            private function createAccessDeniedException(string $message): AccessDeniedHttpException
            {
                return new AccessDeniedHttpException($message);
            }
        };

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Invalid CSRF token.');

        $controller->assertCsrf($form);
    }
}
