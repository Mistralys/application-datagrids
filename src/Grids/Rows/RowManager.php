<?php

declare(strict_types=1);

namespace AppUtils\Grids\Rows;

use AppUtils\Grids\DataGridInterface;
use AppUtils\Grids\Rows\BaseGridRow;
use AppUtils\Grids\Rows\Types\HeaderRow;
use AppUtils\Grids\Rows\Types\MergedRow;
use AppUtils\Interfaces\StringableInterface;
use AppUtils\Grids\Rows\Types\StandardRow;

class RowManager
{
    /**
     * @var GridRowInterface[]
     */
    private array $rows = array();
    private DataGridInterface $grid;

    public function __construct(DataGridInterface $grid)
    {
        $this->grid = $grid;
    }

    public function getGrid(): DataGridInterface
    {
        return $this->grid;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function addArrays(array $rows) : self
    {
        foreach ($rows as $row) {
            $this->addArray($row);
        }

        return $this;
    }

    /**
     * @param array<string, mixed> $columnValues
     */
    public function addArray(array $columnValues) : StandardRow
    {
        $row = new StandardRow($this, $columnValues);

        $this->registerRow($row);

        return $row;
    }

    public function addMerged(string|StringableInterface|NULL $content=null) : MergedRow
    {
        $row = new MergedRow($content);

        $this->registerRow($row);

        return $row;
    }

    public function registerRow(GridRowInterface $row) : self
    {
        if ($row instanceof BaseGridRow) {
            $row->setRowManager($this);
        }
        $this->rows[] = $row;
        return $this;
    }

    /**
     * @return GridRowInterface[]
     */
    public function getRows() : array
    {
        return $this->rows;
    }

    public function isHeaderRowEnabled() : bool
    {
        return $this->grid->options()->isHeaderRowEnabled();
    }

    private ?HeaderRow $headerRow = null;
    private bool $headerRowSet = false;

    /**
     * Gets the header row, if enabled.
     *
     * @return HeaderRow|null
     */
    public function getHeaderRow() : ?HeaderRow
    {
        if($this->headerRowSet) {
            return $this->headerRow;
        }

        $this->headerRowSet = true;

        if($this->isHeaderRowEnabled()) {
            $this->headerRow = new HeaderRow();
            $this->headerRow->setRowManager($this);
        }

        return $this->headerRow;
    }
}
