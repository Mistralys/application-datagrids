<?php

declare(strict_types=1);

namespace AppUtils\Grids\Actions;

use AppUtils\Grids\Actions\Type\GridActionInterface;
use AppUtils\Grids\Actions\Type\RegularAction;
use AppUtils\Grids\Actions\Type\SeparatorAction;
use AppUtils\Grids\Columns\GridColumnInterface;
use AppUtils\Grids\DataGridInterface;

class GridActions
{
    private DataGridInterface $grid;

    /**
     * @var GridActionInterface[]
     */
    private array $actions = array();
    private ?GridColumnInterface $valueColumn = null;

    public function __construct(DataGridInterface $grid)
    {
        $this->grid = $grid;
    }

    public function setValueColumn(string|GridColumnInterface|NULL $column) : self
    {
        if ($column === null) {
            $this->valueColumn = null;
            return $this;
        }

        if (!$column instanceof GridColumnInterface) {
            $column = $this->grid->columns()->getByName($column);
        }

        $this->valueColumn = $column;

        return $this;
    }

    public function getValueColumn(): ?GridColumnInterface
    {
        return $this->valueColumn;
    }

    public function getFormActionFieldName(): string
    {
        return 'grid_action';
    }

    public function getFormSelectionFieldName(): string
    {
        return 'selected';
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
     * @return GridActionInterface[]
     */
    public function getActions() : array
    {
        return $this->actions;
    }

    public function hasActions() : bool
    {
        return !empty($this->actions);
    }

    /**
     * Process a submitted action form.
     *
     * Reads the action name and selected values from $postData (defaults to $_POST),
     * finds the matching RegularAction, invokes its callback if present, and
     * returns true when the action was recognised.
     *
     * @param array<string,mixed>|null $postData  POST data override, or null to read from $_POST.
     * @return bool  True when a matching action was found, false otherwise.
     */
    public function processSubmittedActions(?array $postData = null): bool
    {
        $postData = $postData ?? $_POST;

        $actionFieldName = $this->getFormActionFieldName();

        if (!isset($postData[$actionFieldName])) {
            return false;
        }

        $actionName = (string)$postData[$actionFieldName];
        $selectedValues = isset($postData[$this->getFormSelectionFieldName()])
            ? (array)$postData[$this->getFormSelectionFieldName()]
            : [];

        foreach ($this->actions as $action) {
            if (!$action instanceof RegularAction) {
                continue;
            }

            if ($action->getName() === $actionName) {
                $callback = $action->getCallback();
                if ($callback !== null) {
                    $callback($selectedValues);
                }
                return true;
            }
        }

        return false;
    }
}
