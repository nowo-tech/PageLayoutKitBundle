<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Support;

use Nowo\PageLayoutKitBundle\Form\AbstractPageLayoutFormType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Exposes the protected field helpers of {@see AbstractPageLayoutFormType} to tests.
 *
 * @extends AbstractPageLayoutFormType<mixed>
 */
final class ExposedPageLayoutFormType extends AbstractPageLayoutFormType
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed> $options
     */
    public function buildCkeditorField(FormBuilderInterface $builder, string $name, array $options): void
    {
        $this->withBuilder($builder, function () use ($name, $options): void {
            $this->addCkeditor5Field($name, $options);
        });
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed> $options
     */
    public function buildHiddenLocaleField(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function () use ($options): void {
            $this->addHiddenLocaleField($options);
        });
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed> $options
     */
    public function buildWithDefaultsField(FormBuilderInterface $builder, string $name, string $type, array $options): void
    {
        $this->addWithDefaults($builder, $name, $type, $options);
    }
}
