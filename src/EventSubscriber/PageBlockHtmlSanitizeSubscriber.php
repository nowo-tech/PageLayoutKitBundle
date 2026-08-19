<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\EventSubscriber;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;
use Nowo\PageLayoutKitBundle\Entity\PageCompareBlockTranslation;
use Nowo\PageLayoutKitBundle\Entity\PageCtaBlockTranslation;
use Nowo\PageLayoutKitBundle\Entity\PageTextBlockTranslation;
use Nowo\PageLayoutKitBundle\Security\PageLayoutProtection;

/**
 * Sanitizes block translation HTML on persist/update.
 */
final readonly class PageBlockHtmlSanitizeSubscriber
{
    public function __construct(
        private PageLayoutProtection $protection,
    ) {
    }

    /**
     * @param LifecycleEventArgs<ObjectManager> $args
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        $this->sanitize($args->getObject());
    }

    /**
     * @param LifecycleEventArgs<ObjectManager> $args
     */
    public function preUpdate(LifecycleEventArgs $args): void
    {
        $this->sanitize($args->getObject());
    }

    private function sanitize(object $entity): void
    {
        $sanitizer = $this->protection->htmlSanitizer();

        if ($entity instanceof PageTextBlockTranslation) {
            $body = $entity->getBody();
            if ($body !== '') {
                $entity->setBody($sanitizer->sanitize($body));
            }

            return;
        }

        if ($entity instanceof PageCompareBlockTranslation) {
            if ($entity->getBeforeText() !== '') {
                $entity->setBeforeText($sanitizer->sanitize($entity->getBeforeText()));
            }

            if ($entity->getAfterText() !== '') {
                $entity->setAfterText($sanitizer->sanitize($entity->getAfterText()));
            }

            return;
        }

        if ($entity instanceof PageCtaBlockTranslation) {
            $body = $entity->getBody();
            if ($body !== '') {
                $entity->setBody($sanitizer->sanitize($body));
            }
        }
    }
}
