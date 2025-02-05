<?php

declare(strict_types=1);

namespace AppUtils\Grids\Cells;

use AppUtils\Grids\Columns\GridColumnInterface;
use AppUtils\Grids\Traits\AlignInterface;
use AppUtils\Grids\Traits\IDInterface;
use AppUtils\Interfaces\ClassableInterface;
use WebcomicsBuilder\Grids\Rows\Types\StandardRow;

interface GridCellInterface extends ClassableInterface, IDInterface, AlignInterface
{
    public function setValue(mixed $value) : self;
    public function getValue() : mixed;
    public function getColumn() : GridColumnInterface;
    public function getRow() : StandardRow;
    public function renderContent() : string;
}
