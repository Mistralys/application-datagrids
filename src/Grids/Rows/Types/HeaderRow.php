<?php

declare(strict_types=1);

namespace AppUtils\Grids\Rows\Types;

use AppUtils\Grids\Rows\BaseGridRow;

class HeaderRow extends BaseGridRow
{
    public function getRepeatedID() : ?string
    {
        $id = $this->getID();
        if(!empty($id)) {
            return $id.'-repeated';
        }

        return null;
    }
}
