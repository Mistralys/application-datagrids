<?php

declare(strict_types=1);

namespace AppUtils\Grids\Rows;

use AppUtils\Grids\DataGridException;
use AppUtils\Grids\DataGridInterface;
use AppUtils\Grids\Traits\IDTrait;
use AppUtils\Traits\ClassableTrait;

abstract class BaseGridRow implements GridRowInterface
{
    use ClassableTrait;
    use IDTrait;

    private ?RowManager $manager = null;

    public function setRowManager(RowManager $manager): self
    {
        $this->manager = $manager;
        return $this;
    }

    public function getGrid(): DataGridInterface
    {
        if ($this->manager === null) {
            throw new DataGridException(
                'No row manager set for this row.',
                null,
                DataGridException::ERROR_NO_ROW_MANAGER
            );
        }

        return $this->manager->getGrid();
    }

    public function isSelectable(): bool
    {
        if ($this->manager === null) {
            return false;
        }

        return $this->manager->getGrid()->hasActions();
    }
}
