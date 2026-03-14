<?php

declare(strict_types=1);

namespace AppUtils\Grids\Rows\Types;

use AppUtils\Grids\Cells\RegularCell;
use AppUtils\Grids\Cells\SelectionCell;
use AppUtils\Grids\Columns\GridColumnInterface;
use AppUtils\Grids\DataGridException;
use AppUtils\Grids\Rows\BaseGridRow;
use AppUtils\Grids\Rows\GridRowInterface;
use AppUtils\Grids\Rows\RowManager;

class StandardRow extends BaseGridRow
{
    /**
     * @var array<string, mixed>
     */
    private array $cells = array();
    private ?SelectionCell $selectionCell = null;

    /**
     * @param array<string, mixed> $values
     */
    public function __construct(RowManager $manager, array $values=array())
    {
        $this->setRowManager($manager);
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

    public function getCell(GridColumnInterface|string $column): RegularCell
    {
        $name = $this->resolveName($column);

        if(!isset($this->cells[$name])) {
            $this->cells[$name] = new RegularCell($this, $this->getGrid()->columns()->getByName($name));
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

    public function getSelectValue(): string
    {
        $column = $this->getGrid()->actions()->getValueColumn();
        if ($column === null) {
            return '';
        }
        return (string)$this->getCell($column)->getValue();
    }

    public function getSelectionCell(): ?SelectionCell
    {
        if (!$this->isSelectable()) {
            return null;
        }

        if ($this->getSelectValue() === '') {
            throw new DataGridException(
                'DataGrid: Row selection is active but no value column is configured. '
                . 'Call $grid->actions()->setValueColumn() before rendering.',
                null,
                DataGridException::ERROR_NO_VALUE_COLUMN
            );
        }

        if ($this->selectionCell === null) {
            $this->selectionCell = new SelectionCell($this);
        }
        return $this->selectionCell;
    }
}
