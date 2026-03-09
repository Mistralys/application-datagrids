<?php

declare(strict_types=1);

namespace AppUtils\Grids\Actions;

use AppUtils\Grids\Actions\Type\RegularAction;
use AppUtils\Grids\Actions\Type\SeparatorAction;
use AppUtils\Grids\Columns\GridColumnInterface;
use AppUtils\Grids\DataGrid;

class GridActions
{
    private DataGrid $grid;

    /**
     * @var RegularAction[]
     */
    private array $actions = array();
    private string|null|GridColumnInterface $valueColumn = null;

    public function __construct(DataGrid $grid)
    {
        $this->grid = $grid;
    }

    public function setValueColumn(string|GridColumnInterface|NULL $column) : self
    {
        if(!$column instanceof GridColumnInterface) {
            $column = $this->grid->columns()->getByName($column);
        }

        $this->valueColumn = $column;

        return $this;
    }

    public function add(string $name, string $label) : RegularAction
    {
        $action = new RegularAction($name, $label);
        $this->actions[] = $action;
        return $action;
    }

    public function separator() : self
    {
        $this->actions[] = new SeparatorAction();
        return $this;
    }

    /**
     * @return RegularAction[]
     */
    public function getActions() : array
    {
        return $this->actions;
    }

    public function render() : string
    {
        
    }

    public function hasActions() : bool
    {
        return !empty($this->actions);
    }
}