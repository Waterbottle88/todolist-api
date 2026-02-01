<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class PaginatedResult
{
    /**
     * @param array $items
     * @param int $total
     * @param int $perPage
     * @param int $currentPage
     * @param int $lastPage
     * @param int|null $from
     * @param int|null $to
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $perPage,
        public int $currentPage,
        public int $lastPage,
        public ?int $from = null,
        public ?int $to = null,
    ) {
    }
}
