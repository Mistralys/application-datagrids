<?php

declare(strict_types=1);

namespace AppUtils\Grids\Renderer\Types;

use AppUtils\Grids\Renderer\BaseGridRenderer;
use AppUtils\Grids\Rows\GridRowInterface;

class Bootstrap5Renderer extends BaseGridRenderer
{
    protected function init(): void
    {
        $this->grid->addClass('table');
    }

    public function makeStriped() : self
    {
        $this->grid->addClass('table-striped');
        return $this;
    }

    public function makeHover() : self
    {
        $this->grid->addClass('table-hover');
        return $this;
    }

    public function makeBordered() : self
    {
        $this->grid->addClass('table-bordered');
        return $this;
    }

    public function makeCompact() : self
    {
        $this->grid->addClass('table-sm');
        return $this;
    }

    public function renderCustomRow(GridRowInterface $row, array $columns): string
    {
        return '';
    }
}
