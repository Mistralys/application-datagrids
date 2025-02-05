<?php

declare(strict_types=1);

namespace AppUtils\Grids\Cells;

use AppUtils\Grids\Columns\GridColumnInterface;
use AppUtils\Grids\Traits\AlignTrait;
use AppUtils\Grids\Traits\IDTrait;
use AppUtils\Traits\ClassableTrait;
use WebcomicsBuilder\Grids\Rows\Types\StandardRow;

class GridCell implements GridCellInterface
{
    use IDTrait;
    use ClassableTrait;
    use AlignTrait;

    private mixed $value = null;
    private StandardRow $row;
    private GridColumnInterface $column;

    public function __construct(StandardRow $row, GridColumnInterface $column, mixed $value=null)
    {
        $this->row = $row;
        $this->column = $column;
        $this->setValue($value);
    }

    public function resolveAlign() : ?string
    {
        return $this->getAlign() ?? $this->getColumn()->getAlign();
    }

    public function setValue(mixed $value) : self
    {
        $this->value = $value;
        return $this;
    }

    public function getValue() : mixed
    {
        return $this->value;
    }

    public function getColumn() : GridColumnInterface
    {
        return $this->column;
    }

    public function getRow() : StandardRow
    {
        return $this->row;
    }

    public function renderContent() : string
    {
        return $this->getColumn()->formatValue($this->getValue());
    }
}
