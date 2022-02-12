<?php

declare(strict_types=1);

namespace JohnRogar\FlatApi\Model\Io;

use Generator;

class ResultSet
{
    private int $totalPages;
    private int $totalItems;
    private int $count;

    /**
     * @var array<Dto>
     */
    private iterable $items;

    public function __construct(
        int $totalPages,
        int $totalItems,
        iterable $items = []
    ) {
        $this->totalPages = $totalPages;
        $this->totalItems = $totalItems;
        $this->count = count($items);
        $this->items = $items;
    }

    public function getTotalPages(): int
    {
        return $this->totalPages;
    }

    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * @return Generator<Dto>
     */
    public function getItems(): Generator
    {
        foreach ($this->items as $item) {
            yield $item;
        }
    }

    public function toArray(): array
    {
        $items = [];

        foreach ($this->getItems() as $item) {
            $items[] = $item->getData();
        }

        return [
            'items' => $items,
            'totalPages' => $this->getTotalPages(),
            'totalItems' => $this->getTotalItems(),
        ];
    }
}
