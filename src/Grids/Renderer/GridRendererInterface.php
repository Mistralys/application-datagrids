<?php

declare(strict_types=1);

namespace AppUtils\Grids\Renderer;

use AppUtils\Grids\Cells\GridCell;
use AppUtils\Grids\Columns\GridColumnInterface;
use AppUtils\Grids\Footer\GridFooter;
use AppUtils\Grids\Form\GridForm;
use AppUtils\Grids\Header\GridHeader;
use AppUtils\Grids\Rows\GridRowInterface;
use AppUtils\Grids\Rows\Types\HeaderRow;
use AppUtils\Grids\Rows\Types\MergedRow;
use AppUtils\Interfaces\StringableInterface;
use WebcomicsBuilder\Grids\Rows\Types\StandardRow;

interface GridRendererInterface
{
    public function renderGridFormTop(GridForm $form): string|StringableInterface;
    public function renderGridFormBottom(GridForm $form): string|StringableInterface;
    public function renderGridTop(): string|StringableInterface;
    public function renderGridBottom() : string|StringableInterface;

    /**
     * @param GridRowInterface[] $rows
     * @param GridColumnInterface[] $columns
     * @return string|StringableInterface
     */
    public function renderBody(array $rows, array $columns) : string|StringableInterface;

    public function renderFooterTop(GridFooter $footer): string|StringableInterface;
    public function renderFooterBottom(GridFooter $footer): string|StringableInterface;
    /**
     * @param HeaderRow $row
     * @param GridColumnInterface[] $columns
     * @return string|StringableInterface
     */
    public function renderHeaderRowRepeated(HeaderRow $row, array $columns): string|StringableInterface;

    public function renderHeaderTop(GridHeader $header): string|StringableInterface;
    public function renderHeaderBottom(GridHeader $header): string|StringableInterface;
    /**
     * @param HeaderRow $row
     * @param GridColumnInterface[] $columns
     * @return string|StringableInterface
     */
    public function renderHeaderRow(HeaderRow $row, array $columns): string|StringableInterface;
    public function renderHeaderCell(GridColumnInterface $column): string|StringableInterface;

    /**
     * @param StandardRow $row
     * @param GridColumnInterface[] $columns
     * @return string|StringableInterface
     */
    public function renderStandardRow(StandardRow $row, array $columns): string|StringableInterface;
    public function renderMergedRow(MergedRow $row, int $colspan): string|StringableInterface;

    /**
     * @param GridRowInterface $row
     * @param GridColumnInterface[] $columns
     * @return string|StringableInterface
     */
    public function renderCustomRow(GridRowInterface $row, array $columns): string|StringableInterface;
    public function renderRowCell(GridCell $cell): string|StringableInterface;
}
