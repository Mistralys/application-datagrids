<?php

declare(strict_types=1);

namespace AppUtils\Grids\Renderer\Types;

use AppUtils\Grids\Renderer\BaseGridRenderer;
use AppUtils\Grids\Rows\GridRowInterface;

class DefaultRenderer extends BaseGridRenderer
{
    protected function init(): void
    {
    }

    public function renderCustomRow(GridRowInterface $row, array $columns): string
    {
        return '';
    }
}
