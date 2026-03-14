<?php

declare(strict_types=1);

namespace AppUtils\Grids\Cells;

use AppUtils\Grids\Columns\GridColumnInterface;
use AppUtils\Grids\Traits\AlignInterface;
use AppUtils\Grids\Traits\AlignTrait;
use AppUtils\Grids\Traits\IDInterface;
use AppUtils\Grids\Traits\IDTrait;
use AppUtils\Grids\Rows\Types\StandardRow;

class RegularCell extends BaseCell implements IDInterface, AlignInterface
{
    use IDTrait;
    use AlignTrait;

    private mixed $value = null;

    public function __construct(StandardRow $row, GridColumnInterface $column, mixed $value=null)
    {
        parent::__construct($row, $column);

        $this->setValue($value);
    }

    protected function init(): void
    {
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

    public function renderContent() : string
    {
        return $this->getColumn()->formatValue($this->getValue());
    }
}
