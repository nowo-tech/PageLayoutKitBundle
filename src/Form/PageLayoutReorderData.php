<?php

declare(strict_types=1);

namespace Nowo\PageLayoutKitBundle\Form;

use Nowo\PageLayoutKitBundle\Entity\PageLayoutEntry;

/**
 * Form model for reordering layout entries in the admin UI.
 */
final class PageLayoutReorderData
{
    /** @var list<PageLayoutReorderRowData> */
    public array $rows = [];

    /**
     * @param list<PageLayoutEntry> $entries
     */
    public static function fromEntries(array $entries): self
    {
        $data = new self();
        foreach ($entries as $entry) {
            $data->rows[] = PageLayoutReorderRowData::fromEntry($entry);
        }

        return $data;
    }

    /**
     * @param list<PageLayoutEntry> $entries
     */
    public function applyToEntries(array $entries): void
    {
        $byId = [];
        foreach ($entries as $entry) {
            $id = $entry->getId();
            if ($id !== null) {
                $byId[$id] = $entry;
            }
        }

        foreach ($this->rows as $row) {
            if (!isset($byId[$row->id])) {
                continue;
            }

            $byId[$row->id]->setPosition(max(0, $row->position));
        }
    }
}
