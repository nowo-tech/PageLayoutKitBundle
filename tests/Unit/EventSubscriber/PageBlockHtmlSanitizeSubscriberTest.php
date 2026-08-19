<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Unit\EventSubscriber;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;
use Nowo\PageLayoutKitBundle\Entity\PageCompareBlock;
use Nowo\PageLayoutKitBundle\Entity\PageCompareBlockTranslation;
use Nowo\PageLayoutKitBundle\Entity\PageCtaBlock;
use Nowo\PageLayoutKitBundle\Entity\PageCtaBlockTranslation;
use Nowo\PageLayoutKitBundle\Entity\PageTextBlock;
use Nowo\PageLayoutKitBundle\Entity\PageTextBlockTranslation;
use Nowo\PageLayoutKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageLayoutKitBundle\EventSubscriber\PageBlockHtmlSanitizeSubscriber;
use Nowo\PageLayoutKitBundle\Security\PageLayoutProtection;
use Nowo\PageLayoutKitBundle\Security\PageLayoutProtectionConfig;
use PHPUnit\Framework\TestCase;
use stdClass;

final class PageBlockHtmlSanitizeSubscriberTest extends TestCase
{
    public function testPrePersistSanitizesTextBlockTranslationBody(): void
    {
        $translation = (new PageTextBlockTranslation())
            ->setTranslatable(new PageTextBlock())
            ->setBody('<p>Hi</p><script>alert(1)</script>');

        $this->runPersist($translation);

        self::assertStringContainsString('<p>Hi</p>', $translation->getBody());
        self::assertStringNotContainsString('script', $translation->getBody());
    }

    public function testPreUpdateSanitizesCompareBlockTranslationFields(): void
    {
        $translation = (new PageCompareBlockTranslation())
            ->setTranslatable(new PageCompareBlock())
            ->setBeforeText('<b>Before</b><script>x</script>')
            ->setAfterText('<em>After</em><script>y</script>');

        $this->runUpdate($translation);

        self::assertStringContainsString('<b>Before</b>', $translation->getBeforeText());
        self::assertStringNotContainsString('script', $translation->getBeforeText());
        self::assertStringContainsString('<em>After</em>', $translation->getAfterText());
        self::assertStringNotContainsString('script', $translation->getAfterText());
    }

    public function testPrePersistSanitizesCtaBlockTranslationBody(): void
    {
        $translation = (new PageCtaBlockTranslation())
            ->setTranslatable((new PageCtaBlock())->setSectionKey('cta'))
            ->setBody('<p>CTA</p><script>z</script>');

        $this->runPersist($translation);

        self::assertStringContainsString('<p>CTA</p>', $translation->getBody());
        self::assertStringNotContainsString('script', $translation->getBody());
    }

    public function testSkipsEmptyBodies(): void
    {
        $translation = (new PageTextBlockTranslation())
            ->setTranslatable(new PageTextBlock())
            ->setBody('');

        $this->runPersist($translation);

        self::assertSame('', $translation->getBody());
    }

    public function testIgnoresUnsupportedEntities(): void
    {
        $entity = new stdClass();

        $this->runPersist($entity);

        self::assertInstanceOf(stdClass::class, $entity);
    }

    private function runPersist(object $entity): void
    {
        $subscriber = $this->createSubscriber();
        $args       = $this->createLifecycleArgs($entity);

        $subscriber->prePersist($args);
    }

    private function runUpdate(object $entity): void
    {
        $subscriber = $this->createSubscriber();
        $args       = $this->createLifecycleArgs($entity);

        $subscriber->preUpdate($args);
    }

    private function createSubscriber(): PageBlockHtmlSanitizeSubscriber
    {
        return new PageBlockHtmlSanitizeSubscriber(
            new PageLayoutProtection(new PageLayoutProtectionConfig(HtmlSanitizeStrategy::Allowlist, null)),
        );
    }

    /**
     * @return LifecycleEventArgs<ObjectManager>
     */
    private function createLifecycleArgs(object $entity): LifecycleEventArgs
    {
        $args = $this->createMock(LifecycleEventArgs::class);
        $args->method('getObject')->willReturn($entity);

        return $args;
    }
}
