<?php

declare(strict_types=1);

namespace AppUtils\Grids\Columns;

use AppUtils\Grids\Columns\Types\DefaultColumn;
use AppUtils\Grids\Columns\Types\IntegerColumn;
use AppUtils\Interfaces\StringableInterface;

class ColumnManager
{
    /**
     * @var GridColumnInterface[]
     */
    protected array $columns = array();

    public function add(string $name, string|StringableInterface|NULL $label) : DefaultColumn
    {
        $col = new DefaultColumn($name, $label);
        $this->register($col);
        return $col;
    }

    public function addInteger(string $name, string|StringableInterface|NULL $label) : IntegerColumn
    {
        $col = new IntegerColumn($name, $label);
        $this->register($col);
        return $col;
    }

    public function register(GridColumnInterface $column) : self
    {
        $this->columns[] = $column;
        return $this;
    }

    /**
     * @return GridColumnInterface[]
     */
    public function getColumns() : array
    {
        return $this->columns;
    }

    public function getByName(string $name) : GridColumnInterface
    {
        foreach($this->columns as $column) {
            if($column->getName() === $name) {
                return $column;
            }
        }

        throw new GridColumnException(
            'Unknown data grid column.',
            sprintf(
                'The column with the name [%s] does not exist in the grid.',
                $name
            ),
            GridColumnException::COLUMN_NOT_FOUND_BY_NAME
        );
    }

    public function countColumns() : int
    {
        return count($this->columns);
    }
}
