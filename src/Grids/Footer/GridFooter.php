<?php

declare(strict_types=1);

namespace AppUtils\Grids\Footer;

use AppUtils\Grids\Traits\IDInterface;
use AppUtils\Grids\Traits\IDTrait;
use AppUtils\Interfaces\ClassableInterface;
use AppUtils\Traits\ClassableTrait;

class GridFooter implements ClassableInterface, IDInterface
{
    use ClassableTrait;
    use IDTrait;
}
