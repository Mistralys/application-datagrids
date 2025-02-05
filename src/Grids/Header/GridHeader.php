<?php

declare(strict_types=1);

namespace AppUtils\Grids\Header;

use AppUtils\Grids\Traits\IDInterface;
use AppUtils\Grids\Traits\IDTrait;
use AppUtils\Interfaces\ClassableInterface;
use AppUtils\Traits\ClassableTrait;

class GridHeader implements ClassableInterface, IDInterface
{
    use ClassableTrait;
    use IDTrait;
}
