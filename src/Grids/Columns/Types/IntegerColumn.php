<?php

declare(strict_types=1);

namespace AppUtils\Grids\Columns\Types;

use AppUtils\Grids\Columns\BaseGridColumn;

class IntegerColumn extends BaseGridColumn
{
    protected function init(): void
    {
        $this->alignRight();
        $this->setNowrap();
        $this->setCompact();
    }

    public function formatValue(mixed $value): string
    {
        if(is_numeric($value)) {
            $value = (int)$value;
            return (string)$value;
        }

        return '';
    }
}
