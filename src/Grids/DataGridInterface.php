<?php

declare(strict_types=1);

namespace AppUtils\Grids;

use AppUtils\Grids\Columns\ColumnManager;
use AppUtils\Grids\Columns\GridColumnInterface;
use AppUtils\Grids\Options\GridOptions;
use AppUtils\Grids\Rows\RowManager;
use AppUtils\Interfaces\ClassableInterface;
use AppUtils\Interfaces\RenderableInterface;

interface DataGridInterface extends RenderableInterface, ClassableInterface
{
    public const SORT_ASC = 'ASC';
    public const SORT_DESC = 'DESC';

    public function getID(): string;
    public function options() : GridOptions;
    public function columns(): ColumnManager;
    public function rows(): RowManager;

    /**
     * Gets the column the grid is currently sorted by
     * if any is available and has been selected.
     *
     * @return GridColumnInterface|null
     */
    public function getSortColumn() : ?GridColumnInterface;
    public function getSortDir() : string;
}
