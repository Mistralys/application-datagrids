<?php

declare(strict_types=1);

namespace AppUtils\Grids\Sorting;

use AppUtils\Grids\Columns\GridColumnException;
use AppUtils\Grids\Columns\GridColumnInterface;
use AppUtils\Grids\Columns\SortMode;
use AppUtils\Grids\DataGridInterface;
use AppUtils\Grids\Rows\GridRowInterface;
use AppUtils\Grids\Rows\Types\StandardRow;

class SortManager implements SortManagerInterface
{
    private DataGridInterface $grid;
    private string $columnParam = 'sort';
    private string $dirParam = 'sort_dir';
    private bool $resolved = false;
    private ?GridColumnInterface $sortColumn = null;
    private string $sortDir;

    public function __construct(DataGridInterface $grid)
    {
        $this->grid = $grid;
        $this->sortDir = DataGridInterface::SORT_ASC;
    }

    // -------------------------------------------------------------------------
    // SortManagerInterface
    // -------------------------------------------------------------------------

    public function getSortColumn(): ?GridColumnInterface
    {
        $this->resolveSortState();
        return $this->sortColumn;
    }

    public function getSortDir(): string
    {
        $this->resolveSortState();
        return $this->sortDir;
    }

    /**
     * @param GridColumnInterface $column
     * @return string The sort URL. When used outside HTMLTag contexts, callers must apply htmlspecialchars() to prevent XSS.
     */
    public function getSortURL(GridColumnInterface $column): string
    {
        $currentCol = $this->getSortColumn();
        $currentDir = $this->getSortDir();

        if ($currentCol !== null && $currentCol->getName() === $column->getName()) {
            $dir = ($currentDir === DataGridInterface::SORT_ASC)
                ? DataGridInterface::SORT_DESC
                : DataGridInterface::SORT_ASC;
        } else {
            $dir = DataGridInterface::SORT_ASC;
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $parts = parse_url($uri);
        $path = isset($parts['path']) ? (string)$parts['path'] : '/';
        $query = isset($parts['query']) ? (string)$parts['query'] : '';

        parse_str($query, $params);
        $params[$this->columnParam] = $column->getName();
        $params[$this->dirParam] = $dir;
        $qs = http_build_query($params);

        return $path . ($qs !== '' ? '?' . $qs : '');
    }

    public function isSortedBy(GridColumnInterface $column): bool
    {
        $current = $this->getSortColumn();
        return $current !== null && $current->getName() === $column->getName();
    }

    public function setColumnParam(string $param): self
    {
        $this->columnParam = $param;
        return $this;
    }

    public function setDirectionParam(string $param): self
    {
        $this->dirParam = $param;
        return $this;
    }

    public function getColumnParam(): string
    {
        return $this->columnParam;
    }

    public function getDirectionParam(): string
    {
        return $this->dirParam;
    }

    // -------------------------------------------------------------------------
    // Row sorting
    // -------------------------------------------------------------------------

    /**
     * Sorts the rows in-place.
     *
     * Only rows that are StandardRow instances are reordered; non-standard
     * rows (e.g. MergedRow) remain at their original positions in the array.
     * Manual sort mode is a no-op — the consumer is responsible for ordering.
     *
     * @param GridRowInterface[] $rows
     */
    public function sortRows(array &$rows): void
    {
        $col = $this->getSortColumn();

        if ($col === null) {
            return;
        }

        $mode = $col->getSortMode();

        if ($mode === SortMode::Manual) {
            return;
        }

        // Partition: collect the original indices of StandardRows.
        $standardIndices = array();
        $standardRows = array();

        foreach ($rows as $index => $row) {
            if ($row instanceof StandardRow) {
                $standardIndices[] = $index;
                $standardRows[] = $row;
            }
        }

        if (count($standardRows) < 2) {
            return;
        }

        $dir = $this->getSortDir();

        if ($mode === SortMode::Native) {
            usort($standardRows, function (StandardRow $a, StandardRow $b) use ($col, $dir): int {
                $valA = $a->getCell($col)->getValue();
                $valB = $b->getCell($col)->getValue();
                $result = $this->compareValues($valA, $valB);
                return $dir === DataGridInterface::SORT_DESC ? -$result : $result;
            });
        } elseif ($mode === SortMode::Callback) {
            $callback = $col->getSortCallback();
            if ($callback !== null) {
                usort($standardRows, function (StandardRow $a, StandardRow $b) use ($callback, $col, $dir): int {
                    $result = (int)$callback($a, $b, $col);
                    return $dir === DataGridInterface::SORT_DESC ? -$result : $result;
                });
            }
        }

        // Reassemble: put sorted standard rows back into their original positions.
        foreach ($standardIndices as $i => $originalIndex) {
            $rows[$originalIndex] = $standardRows[$i];
        }
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private function resolveSortState(): void
    {
        if ($this->resolved) {
            return;
        }

        $this->resolved = true;

        $columnName = isset($_GET[$this->columnParam]) ? (string)$_GET[$this->columnParam] : '';

        if ($columnName === '') {
            return;
        }

        try {
            $column = $this->grid->columns()->getByName($columnName);
        } catch (GridColumnException) {
            return;
        }

        if (!$column->isSortable()) {
            return;
        }

        $this->sortColumn = $column;

        $rawDir = isset($_GET[$this->dirParam]) ? strtoupper((string)$_GET[$this->dirParam]) : '';

        if ($rawDir === DataGridInterface::SORT_DESC) {
            $this->sortDir = DataGridInterface::SORT_DESC;
        } else {
            $this->sortDir = DataGridInterface::SORT_ASC;
        }
    }

    private function compareValues(mixed $a, mixed $b): int
    {
        $isNumericA = is_numeric($a);
        $isNumericB = is_numeric($b);

        if ($isNumericA && $isNumericB) {
            return (float)$a <=> (float)$b;
        }

        if (is_string($a) && is_string($b)) {
            return strcasecmp($a, $b);
        }

        return strcasecmp((string)$a, (string)$b);
    }
}
