<?php

declare(strict_types=1);

namespace AppUtils\Grids\Cells;

use AppUtils\HTMLTag;
use AppUtils\Grids\Rows\Types\StandardRow;

/**
 * A checkbox cell prepended to each selectable row.
 *
 * Unlike regular cells this cell is not associated with any grid column; it
 * therefore does not extend BaseCell or implement GridCellInterface.
 * The renderer accesses it directly via StandardRow::getSelectionCell().
 */
class SelectionCell
{
    private StandardRow $row;

    public function __construct(StandardRow $row)
    {
        $this->row = $row;
    }

    public function getRow(): StandardRow
    {
        return $this->row;
    }

    public function renderContent(): string
    {
        $actions = $this->row->getGrid()->actions();

        return (string)HTMLTag::create('input')
            ->attr('type', 'checkbox')
            ->attr('name', $actions->getFormSelectionFieldName() . '[]')
            ->attr('value', $this->row->getSelectValue());
    }
}
