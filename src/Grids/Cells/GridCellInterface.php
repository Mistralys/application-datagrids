<?php

declare(strict_types=1);

namespace AppUtils\Grids\Cells;

use AppUtils\Grids\Columns\GridColumnInterface;
use AppUtils\Grids\DataGridInterface;
use AppUtils\Interfaces\ClassableInterface;
use WebcomicsBuilder\Grids\Rows\Types\StandardRow;

interface GridCellInterface extends ClassableInterface
{
    public function getColumn() : GridColumnInterface;
    public function getRow() : StandardRow;
    public function getGrid() : DataGridInterface;
    public function renderContent() : string;
}
