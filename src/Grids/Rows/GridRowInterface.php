<?php

declare(strict_types=1);

namespace AppUtils\Grids\Rows;

use AppUtils\Grids\DataGridInterface;
use AppUtils\Grids\Traits\IDInterface;
use AppUtils\Interfaces\ClassableInterface;

interface GridRowInterface extends ClassableInterface, IDInterface
{
    public function getGrid() : DataGridInterface;
    public function isSelectable() : bool;
}
