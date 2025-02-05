<?php

declare(strict_types=1);

namespace AppUtils\Grids\Columns;

use AppUtils\Grids\DataGridException;

class GridColumnException extends DataGridException
{
    public const INVALID_ALIGN_VALUE = 171601;
    public const INVALID_COLUMN_NAME = 171602;
    public const COLUMN_NOT_FOUND_BY_NAME = 171603;
}
