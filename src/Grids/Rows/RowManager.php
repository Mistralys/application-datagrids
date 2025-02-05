<?php

declare(strict_types=1);

namespace AppUtils\Grids\Rows;

use AppUtils\Grids\DataGrid;
use AppUtils\Grids\Rows\Types\HeaderRow;
use AppUtils\Grids\Rows\Types\MergedRow;
use WebcomicsBuilder\Grids\Rows\Types\StandardRow;

class RowManager
{
    /**
     * @var GridRowInterface[]
     */
    private array $rows = array();
    private DataGrid $grid;

    public function __construct(DataGrid $grid)
    {
        $this->grid = $grid;
    }

    public function getGrid(): DataGrid
    {
        return $this->grid;
    }

    public function addArrays(array $rows) : self
    {
        foreach ($rows as $row) {
            $this->addArray($row);
        }

        return $this;
    }

    public function addArray(array $columnValues) : StandardRow
    {
        $row = new StandardRow($this, $columnValues);

        $this->registerRow($row);

        return $row;
    }

    public function addMerged($content=null) : MergedRow
    {
        $row = new MergedRow($content);

        $this->registerRow($row);

        return $row;
    }

    public function registerRow(GridRowInterface $row) : self
    {
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
        }

        return $this->headerRow;
    }
}
