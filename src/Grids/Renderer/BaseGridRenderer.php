<?php

declare(strict_types=1);

namespace AppUtils\Grids\Renderer;

use AppUtils\Grids\Cells\GridCell;
use AppUtils\Grids\Columns\GridColumnInterface;
use AppUtils\Grids\DataGridInterface;
use AppUtils\Grids\Footer\GridFooter;
use AppUtils\Grids\Form\GridForm;
use AppUtils\Grids\Header\GridHeader;
use AppUtils\Grids\Rows\Types\HeaderRow;
use AppUtils\Grids\Rows\Types\MergedRow;
use AppUtils\Grids\Traits\AlignInterface;
use AppUtils\HTMLTag;
use AppUtils\Interfaces\StringableInterface;
use WebcomicsBuilder\Grids\Rows\Types\StandardRow;

abstract class BaseGridRenderer implements GridRendererInterface
{
    protected DataGridInterface $grid;

    public function __construct(DataGridInterface $grid)
    {
        $this->grid = $grid;

        $this->init();
    }

    abstract protected function init() : void;

    public function renderGridFormBottom(GridForm $form): string|StringableInterface
    {
        return $this->createForm($form)->renderClose();
    }

    public function renderGridFormTop(GridForm $form): string|StringableInterface
    {
        return $this->createForm($form)->renderOpen();
    }

    protected function createForm(GridForm $form) : HTMLTag
    {
        return HTMLTag::create('form')
            ->id($form->requireID())
            ->attr('method', 'post')
            ->addClasses($form->getClasses())
            ->setContent($this->createHiddenVariableWrapper($form));
    }

    protected function createHiddenVariableWrapper(GridForm $form) : HTMLTag
    {
        return HTMLTag::create('div')
            ->addClass('hiddens')
            ->setEmptyAllowed()
            ->setContent(implode(PHP_EOL, $form->getHiddenVars()));
    }

    public function renderGridTop(): string|StringableInterface
    {
        return
            HTMLTag::create('div')
                ->addClass('grid-container')
                ->renderOpen().
            HTMLTag::create('table')
                ->id($this->grid->getID())
                ->addClasses($this->grid->getClasses())
                ->renderOpen();
    }

    public function renderGridBottom() : string|StringableInterface
    {
        return '</table></div>';
    }

    public function renderBody(array $rows, array $columns): string|StringableInterface
    {
        $content = '';

        foreach($rows as $row) {
            if($row instanceof MergedRow) {
                $content .= $this->renderMergedRow($row, $this->grid->columns()->countColumns());
            } else if($row instanceof StandardRow) {
                $content .= $this->renderStandardRow($row, $columns);
            } else {
                $content .= $this->renderCustomRow($row, $columns);
            }
        }

        return (string)HTMLTag::create('tbody')
            ->setContent($content);
    }

    public function renderHeaderTop(GridHeader $header) : string|StringableInterface
    {
        return $this->createHeader($header)->renderOpen();
    }

    protected function createHeader(GridHeader $header) : HTMLTag
    {
        return HTMLTag::create('thead')
            ->id($header->getID())
            ->addClasses($header->getClasses());
    }

    public function renderHeaderRow(HeaderRow $row, array $columns): string|StringableInterface
    {
        return $this->createHeaderRow($row, $columns);
    }

    /**
     * @param HeaderRow $row
     * @param GridColumnInterface[] $columns
     * @return HTMLTag
     */
    protected function createHeaderRow(HeaderRow $row, array $columns): HTMLTag
    {
        return HTMLTag::create('tr')
            ->id($row->getID())
            ->addClasses($row->getClasses())
            ->setContent($this->renderHeaderCells($columns));
    }

    public function renderHeaderRowRepeated(HeaderRow $row, array $columns): string|StringableInterface
    {
        return $this->createHeaderRowRepeated($row, $columns);
    }

    /**
     * @param HeaderRow $row
     * @param GridColumnInterface[] $columns
     * @return HTMLTag
     */
    protected function createHeaderRowRepeated(HeaderRow $row, array $columns): HTMLTag
    {
        return HTMLTag::create('tr')
            ->id($row->getRepeatedID())
            ->addClasses($row->getClasses())
            ->setContent($this->renderHeaderCells($columns));
    }

    public function renderHeaderCell(GridColumnInterface $column): string|StringableInterface
    {
        return $this->createHeaderCell($column);
    }

    protected function createHeaderCell(GridColumnInterface $column): HTMLTag
    {
        $tag = HTMLTag::create('th')
            ->id($column->getID())
            ->addClasses($column->getClasses())
            ->style('text-align', $column->getAlign() ?? AlignInterface::ALIGN_LEFT)
            ->setEmptyAllowed()
            ->setContent($column->getLabel());

        if($column->isNowrap()) {
            $tag->style('white-space', 'nowrap');
        }

        if($column->isCompact()) {
            $tag->style('width', '1%');
        }

        return $tag;
    }

    public function renderFooterTop(GridFooter $footer) : string|StringableInterface
    {
        return $this->createFooter($footer)->renderOpen();
    }

    protected function createFooter(GridFooter $footer) : HTMLTag
    {
        return HTMLTag::create('tfoot')
            ->id($footer->getID())
            ->addClasses($footer->getClasses());
    }

    public function renderFooterBottom(GridFooter $footer): string|StringableInterface
    {
        return $this->createFooter($footer)->renderClose();
    }

    /**
     * @param GridColumnInterface[] $columns
     * @return string|StringableInterface
     */
    protected function renderHeaderCells(array $columns) : string|StringableInterface
    {
        $output = '';

        foreach($columns as $column) {
            $output .= $this->renderHeaderCell($column);
        }

        return $output;
    }

    public function renderHeaderBottom(GridHeader $header): string|StringableInterface
    {
        return $this->createHeader($header)->renderClose();
    }

    public function renderStandardRow(StandardRow $row, array $columns): string|StringableInterface
    {
        return $this->createStandardRow($row, $columns);
    }

    /**
     * @param StandardRow $row
     * @param GridColumnInterface[] $columns
     * @return HTMLTag
     */
    protected function createStandardRow(StandardRow $row, array $columns) : HTMLTag
    {
        return HTMLTag::create('tr')
            ->id($row->getID())
            ->addClasses($row->getClasses())
            ->setContent($this->renderStandardRowCells($row, $columns));
    }

    /**
     * @param StandardRow $row
     * @param GridColumnInterface[] $columns
     * @return string|StringableInterface
     */
    public function renderStandardRowCells(StandardRow $row, array $columns) : string|StringableInterface
    {
        $output = '';

        foreach($columns as $column) {
            $cell = $row->getCell($column);
            $output .= $this->renderRowCell($cell);
        }

        return $output;
    }

    public function renderMergedRow(MergedRow $row, int $colspan): string|StringableInterface
    {
        return $this->createMergedRow($row, $colspan);
    }

    protected function createMergedRow(MergedRow $row, int $colspan) : HTMLTag
    {
        return HTMLTag::create('tr')
            ->id($row->getID())
            ->addClasses($row->getClasses())
            ->setContent($this->createMergedRowCell($row, $colspan));
    }

    protected function createMergedRowCell(MergedRow $row, int $colspan) : HTMLTag
    {
        return HTMLTag::create('td')
            ->attr('colspan', (string)$colspan)
            ->setContent($row->getContent());
    }

    public function renderRowCell(GridCell $cell): string|StringableInterface
    {
        return $this->createRowCell($cell);
    }

    protected function createRowCell(GridCell $cell) : HTMLTag
    {
        $tag = HTMLTag::create('td')
            ->id($cell->getID())
            ->addClasses($cell->getClasses())
            ->style('text-align', $cell->resolveAlign())
            ->setEmptyAllowed()
            ->setContent($cell->renderContent());

        $column = $cell->getColumn();

        if($column->isNowrap()) {
            $tag->style('white-space', 'nowrap');
        }

        if($column->isCompact()) {
            $tag->style('width', '1%');
        }

        return $tag;
    }
}
