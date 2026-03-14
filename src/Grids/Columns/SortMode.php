<?php

declare(strict_types=1);

namespace AppUtils\Grids\Columns;

enum SortMode: string
{
    case Native = 'native';
    case Callback = 'callback';
    case Manual = 'manual';
}
