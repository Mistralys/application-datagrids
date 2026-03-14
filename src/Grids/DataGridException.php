<?php

declare(strict_types=1);

namespace AppUtils\Grids;

use AppUtils\BaseException;

class DataGridException extends BaseException
{
    public const ERROR_NO_PAGINATION_PROVIDER = 171700;
    public const ERROR_NO_VALUE_COLUMN = 171701;
    public const ERROR_NO_ROW_MANAGER = 171702;
    public const int ERROR_INVALID_GRID_ID = 260301;
    public const int ERROR_INVALID_ITEMS_PER_PAGE = 260302;
}
