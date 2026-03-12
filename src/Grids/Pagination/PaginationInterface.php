<?php

declare(strict_types=1);

namespace AppUtils\Grids\Pagination;

interface PaginationInterface
{
    public function getTotalItems(): int;

    public function getItemsPerPage(): int;

    public function getCurrentPage(): int;

    public function getPageURL(int $page): string;
}
