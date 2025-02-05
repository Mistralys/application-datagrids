<?php

declare(strict_types=1);

namespace AppUtils\Grids\Columns\Types;

use AppUtils\Grids\Columns\BaseGridColumn;

class DefaultColumn extends BaseGridColumn
{
    protected function init(): void
    {
    }

    public function formatValue(mixed $value): string
    {
        if($value === NULL || $value === '' || is_scalar($value)) {
            return (string)$value;
        }

        return '';
    }
}
