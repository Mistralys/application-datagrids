<?php

declare(strict_types=1);

namespace AppUtils\Grids\Rows;

use AppUtils\Grids\Traits\IDTrait;
use AppUtils\Traits\ClassableTrait;

abstract class BaseGridRow implements GridRowInterface
{
    use ClassableTrait;
    use IDTrait;
}
