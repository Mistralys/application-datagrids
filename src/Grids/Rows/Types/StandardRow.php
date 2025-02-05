<?php

declare(strict_types=1);

namespace WebcomicsBuilder\Grids\Rows\Types;

use AppUtils\Grids\Cells\GridCell;
use AppUtils\Grids\Columns\GridColumnInterface;
use AppUtils\Grids\Rows\BaseGridRow;
use AppUtils\Grids\Rows\GridRowInterface;
use AppUtils\Grids\Rows\RowManager;

class StandardRow extends BaseGridRow
{
    /**
     * @var array<string, mixed>
     */
    private array $cells = array();
    private RowManager $manager;

    /**
     * @param array<string, mixed> $values
     */
    public function __construct(RowManager $manager, array $values=array())
    {
        $this->manager = $manager;
        $this->setValues($values);
    }

    /**
     * @param GridColumnInterface|string $column
     * @param mixed $value
     * @return $this
     */
    public function setValue(GridColumnInterface|string $column, mixed $value): self
    {
        $this->getCell($column)->setValue($value);
        return $this;
    }

    public function getCell(GridColumnInterface|string $column): GridCell
    {
        $name = $this->resolveName($column);

        if(!isset($this->cells[$name])) {
            $this->cells[$name] = new GridCell($this, $this->manager->getGrid()->columns()->getByName($name));
        }

        return $this->cells[$name];
    }

    private function resolveName(GridColumnInterface|string $column) : string
    {
        if($column instanceof GridColumnInterface) {
            return $column->getName();
        }

        return $column;
    }

    public function getValue(GridColumnInterface|string $column): mixed
    {
        return $this->cells[$this->resolveName($column)] ?? null;
    }

    /**
     * @param array<string, mixed> $values
     * @return $this
     */
    public function setValues(array $values): GridRowInterface
    {
        foreach($values as $column => $value) {
            $this->setValue($column, $value);
        }

        return $this;
    }
}
