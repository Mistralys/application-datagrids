<?php

declare(strict_types=1);

namespace AppUtils\Grids\Columns;

use AppUtils\Grids\DataGridInterface;
use AppUtils\Grids\Traits\AlignInterface;
use AppUtils\Grids\Traits\IDInterface;
use AppUtils\Interfaces\ClassableInterface;
use AppUtils\NumberInfo;

interface GridColumnInterface extends ClassableInterface, IDInterface, AlignInterface
{

    public function getName() : string;
    public function getLabel() : string;
    public function setNowrap(bool $nowrap=true) : self;
    public function isNowrap() : bool;
    public function setCompact(bool $compact=true) : self;

    /**
     * Sets the column as sortable, and enabled the native
     * column sorting, according to the column type.
     *
     * @return self
     */
    public function useNativeSorting() : self;

    /**
     * Sets the column as sortable, and enables the manual
     * column sorting: It is up to the application to
     * implement the row sorting.
     *
     * > NOTE: Use {@see DataGridInterface::getSortColumn()}
     * to determine if rows should be sorted by this column.
     *
     * @return self
     */
    public function useManualSorting() : self;

    /**
     * Sets the column as sortable, and uses the specified
     * callback to sort the rows by the values in this column.
     *
     * @param callable $callback
     * @return self
     */
    public function useCallbackSorting(callable $callback) : self;
    public function isSortable() : bool;
    public function isCompact() : bool;
    public function setWidth(int|string|NumberInfo|NULL $width) : self;
    public function getWidth() : ?NumberInfo;
    public function formatValue(mixed $value) : string;
}
