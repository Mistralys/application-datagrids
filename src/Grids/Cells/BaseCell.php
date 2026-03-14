<?php

declare(strict_types=1);

namespace AppUtils\Grids\Cells;

use AppUtils\Grids\Columns\GridColumnInterface;
use AppUtils\Grids\DataGridInterface;
use AppUtils\Traits\ClassableTrait;
use AppUtils\Grids\Rows\Types\StandardRow;

abstract class BaseCell implements GridCellInterface
{
    use ClassableTrait;

    private StandardRow $row;
    private GridColumnInterface $column;

    public function __construct(StandardRow $row, GridColumnInterface $column)
    {
        $this->row = $row;
        $this->column = $column;

        $this->init();
    }

    abstract protected function init() : void;

    public function getColumn() : GridColumnInterface
    {
        return $this->column;
    }

    public function getRow() : StandardRow
    {
        return $this->row;
    }

    public function getGrid(): DataGridInterface
    {
        return $this->row->getGrid();
    }
}
