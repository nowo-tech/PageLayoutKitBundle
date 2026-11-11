<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Tests\Integration\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;
use Nowo\PageLayoutKitBundle\Service\PageLayoutProviderInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function count;

/**
 * Host controllers of the worker test: a failing bundle write (closes the manager) and a public read.
 */
final readonly class PersistenceTestController
{
    public function __construct(
        private ManagerRegistry $registry,
        private PageLayoutProviderInterface $provider,
    ) {
    }

    /**
     * A write that fails inside a transaction: Doctrine closes the manager of the bundle entities.
     */
    public function fail(Request $request): Response
    {
        $manager = $this->registry->getManagerForClass(PageLayoutEntry::class);
        if (!$manager instanceof EntityManagerInterface) {
            return new Response('no manager', 500);
        }

        $fail = $request->query->getBoolean('fail', true);
        $manager->wrapInTransaction(static function () use ($fail): void {
            if ($fail) {
                throw new RuntimeException('Simulated failed write.');
            }
        });

        return new Response('no failure requested');
    }

    public function layout(): Response
    {
        return new Response((string) count($this->provider->getLayout('home', 'en')));
    }
}
