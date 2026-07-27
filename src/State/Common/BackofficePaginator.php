<?php

namespace App\State\Common;

use ApiPlatform\State\Pagination\PaginatorInterface;

/**
 * @template T of object
 *
 * @implements PaginatorInterface<T>
 * @implements \IteratorAggregate<mixed, T>
 */
final class BackofficePaginator implements PaginatorInterface, \IteratorAggregate
{
    /** @param list<T> $items */
    public function __construct(
        private readonly array $items,
        private readonly int $page,
        private readonly int $itemsPerPage,
        private readonly int $totalItems,
    ) {
    }

    public function getLastPage(): float
    {
        return (float) max(1, (int) ceil($this->totalItems / $this->itemsPerPage));
    }

    public function getTotalItems(): float
    {
        return (float) $this->totalItems;
    }

    public function getCurrentPage(): float
    {
        return (float) $this->page;
    }

    public function getItemsPerPage(): float
    {
        return (float) $this->itemsPerPage;
    }

    public function count(): int
    {
        return $this->totalItems;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }
}
