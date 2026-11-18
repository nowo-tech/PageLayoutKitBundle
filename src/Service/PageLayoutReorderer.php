<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Service;

use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;
use Nowo\PageLayoutKitBundle\Event\PageLayoutChangedEvent;
use Nowo\PageLayoutKitBundle\Form\PageLayoutReorderData;
use Nowo\PageLayoutKitBundle\Persistence\PageLayoutTransaction;
use Nowo\PageLayoutKitBundle\Repository\PageLayoutEntryRepository;
use Nowo\TransactionContract\TransientStorageFailure;

use function array_filter;
use function array_map;
use function array_values;
use function usort;

/**
 * The layout reorder write unit of the admin UI (2.3, REQ-RUNTIME-003 rules 1 and 4).
 *
 * One transaction on the manager that maps the bundle entities: the page's entries are locked `FOR UPDATE` in id
 * order and read again under the lock, then the submitted positions are applied to its enabled entries. The submitted
 * order therefore replaces the committed one whole: two admins, or an admin and the migration, never interleave
 * (the later save wins entirely, never a mix of both orders). The ID-only {@see PageLayoutChangedEvent} follows the
 * commit (2.2). Stateless.
 *
 * @internal
 */
final readonly class PageLayoutReorderer
{
    public function __construct(
        private PageLayoutEntryRepository $pageLayoutEntryRepository,
        private PageLayoutTransaction $transaction,
    ) {
    }

    /**
     * @throws TransientStorageFailure When a lock conflict rolled the unit back (nothing changed)
     *
     * @return list<int> The page's enabled entry ids in their new order
     */
    public function reorder(string $pageKey, ?PageLayoutReorderData $data): array
    {
        $ids = $this->transaction->run(fn (): array => $this->applyLocked($pageKey, $data));
        $this->transaction->record(new PageLayoutChangedEvent($pageKey, $ids));

        return $ids;
    }

    /**
     * Inside the unit: lock and re-read the page, apply the submitted positions to its enabled entries.
     *
     * @return list<int>
     */
    private function applyLocked(string $pageKey, ?PageLayoutReorderData $data): array
    {
        $enabled = array_values(array_filter(
            $this->pageLayoutEntryRepository->lockByPageKey($pageKey),
            static fn (PageLayoutEntry $entry): bool => $entry->isEnabled(),
        ));
        $data?->applyToEntries($enabled);
        usort($enabled, static fn (PageLayoutEntry $a, PageLayoutEntry $b): int => $a->getPosition() <=> $b->getPosition());

        return array_values(array_filter(
            array_map(static fn (PageLayoutEntry $entry): ?int => $entry->getId(), $enabled),
            static fn (?int $id): bool => $id !== null,
        ));
    }
}
