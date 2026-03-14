<?php

declare(strict_types=1);

namespace AppUtils\Grids\Sorting;

use AppUtils\Grids\Columns\GridColumnInterface;
use AppUtils\Grids\Rows\GridRowInterface;

interface SortManagerInterface
{
    /**
     * @param GridRowInterface[] $rows
     */
    public function sortRows(array &$rows): void;
    public function getSortColumn(): ?GridColumnInterface;
    public function getSortDir(): string;
    /**
     * @param GridColumnInterface $column
     * @return string The sort URL. When used outside HTMLTag contexts, callers must apply htmlspecialchars() to prevent XSS.
     */
    public function getSortURL(GridColumnInterface $column): string;
    public function isSortedBy(GridColumnInterface $column): bool;
    public function setColumnParam(string $param): self;
    public function setDirectionParam(string $param): self;
    public function getColumnParam(): string;
    public function getDirectionParam(): string;
}
