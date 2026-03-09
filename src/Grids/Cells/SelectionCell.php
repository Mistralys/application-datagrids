<?php

declare(strict_types=1);

namespace AppUtils\Grids\Cells;

use AppUtils\HTMLTag;
use AppUtils\JSHelper;

class SelectionCell extends BaseCell
{
    private string $id;

    protected function init(): void
    {
        $this->id = 'gc'.JSHelper::nextElementID();
    }

    public function getID() : string
    {
        return $this->id;
    }

    public function renderContent(): string
    {
        return (string)HTMLTag::create('input')
            ->id($this->getID())
            ->attr('type', 'checkbox')
            ->attr('name', 'selected[]')
            ->attr('value', $this->getRow()->getSelectValue());
    }
}
