<?php

declare(strict_types=1);

namespace AppUtils\Grids\Renderer;

use AppUtils\Grids\Actions\GridActions;
use AppUtils\Grids\Actions\Type\RegularAction;
use AppUtils\Grids\Actions\Type\SeparatorAction;
use AppUtils\Grids\Cells\RegularCell;
use AppUtils\Grids\Cells\SelectionCell;
use AppUtils\Grids\Columns\GridColumnInterface;
use AppUtils\Grids\DataGridInterface;
use AppUtils\Grids\Pagination\GridPagination;
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
                $content .= $this->renderMergedRow($row, $this->getColspan());
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

    public function renderActionsRow(GridActions $actions): string|StringableInterface
    {
        $items = $actions->getActions();
        if(empty($items)) {
            return '';
        }

        $placeholder = HTMLTag::create('option')
            ->attr('value', '')
            ->attr('disabled', 'disabled')
            ->attr('selected', 'selected')
            ->setContent('Select action…');

        $select = HTMLTag::create('select')
            ->attr('name', $actions->getFormActionFieldName())
            ->appendContent($placeholder);

        foreach($items as $item) {
            if($item instanceof SeparatorAction) {
                $select->appendContent($this->renderSeparatorAction($item));
                continue;
            }

            if($item instanceof RegularAction) {
                $select->appendContent($this->renderActionOption($item));
                continue;
            }
        }

        $button = HTMLTag::create('button')
            ->attr('type', 'submit')
            ->setContent('Apply');

        return HTMLTag::create('tr')
            ->setContent(HTMLTag::create('td')
                ->attr('colspan', (string)$this->getColspan())
                ->appendContent($select)
                ->appendContent($button));
    }

    public function renderSeparatorAction(SeparatorAction $action) : string|StringableInterface
    {
        return HTMLTag::create('option')
            ->addClass('separator')
            ->setContent('-----');
    }

    public function renderActionOption(RegularAction $action) : string|StringableInterface
    {
        return HTMLTag::create('option')
            ->attr('value', $action->getName())
            ->setContent($action->getLabel());
    }

    /**
     * @param GridColumnInterface[] $columns
     * @return string|StringableInterface
     */
    protected function renderHeaderCells(array $columns) : string|StringableInterface
    {
        $output = '';

        if ($this->grid->hasActions()) {
            $output .= $this->renderSelectionHeaderCell();
        }

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

        if($row->isSelectable()) {
            $cell = $row->getSelectionCell();
            if ($cell !== null) {
                $output .= $this->renderSelectionCell($cell);
            }
        }

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

    public function renderRowCell(RegularCell $cell): string|StringableInterface
    {
        return $this->createRowCell($cell);
    }

    protected function createRowCell(RegularCell $cell) : HTMLTag
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

    // =========================================================================
    // Colspan helper (WP-002)
    // =========================================================================

    protected function getColspan(): int
    {
        $count = $this->grid->columns()->countColumns();

        if ($this->grid->hasActions()) {
            $count++;
        }

        return $count;
    }

    // =========================================================================
    // Selection cell rendering (WP-002)
    // =========================================================================

    public function renderSelectionHeaderCell(): string|StringableInterface
    {
        return $this->createSelectionHeaderCell();
    }

    protected function createSelectionHeaderCell(): HTMLTag
    {
        $gridId = $this->grid->getID();
        $checkboxId = 'grid-' . $gridId . '-select-all';

        return HTMLTag::create('th')
            ->setContent(
                HTMLTag::create('input')
                    ->attr('type', 'checkbox')
                    ->id($checkboxId)
                    ->attr('onclick', "this.closest('table').querySelectorAll('input[name=\"selected[]\"]').forEach(cb => cb.checked = this.checked)")
            );
    }

    public function renderSelectionCell(SelectionCell $cell): string|StringableInterface
    {
        return $this->createSelectionCell($cell);
    }

    protected function createSelectionCell(SelectionCell $cell): HTMLTag
    {
        return HTMLTag::create('td')
            ->setContent($cell->renderContent());
    }

    // =========================================================================
    // Pagination rendering (WP-005)
    // =========================================================================

    public function renderPaginationRow(GridPagination $pagination): string|StringableInterface
    {
        if (!$pagination->hasProvider() || $pagination->getTotalPages() <= 1) {
            return '';
        }

        return $this->createPaginationRow($pagination);
    }

    protected function createPaginationRow(GridPagination $pagination): HTMLTag
    {
        $nav = HTMLTag::create('nav')
            ->appendContent($this->createPreviousLink($pagination));

        foreach ($pagination->getPageNumbers() as $page) {
            if ($page === null) {
                $nav->appendContent($this->createEllipsis());
            } else {
                $isCurrent = $page === $pagination->getCurrentPage();
                $url = $isCurrent ? '' : $pagination->getPageURL($page);
                $nav->appendContent($this->createPageLink($page, $url, $isCurrent));
            }
        }

        $nav->appendContent($this->createNextLink($pagination));

        $td = HTMLTag::create('td')
            ->attr('colspan', (string)$this->getColspan())
            ->appendContent($nav);

        if ($pagination->isPageJumpEnabled()) {
            $td->appendContent($this->createPageJumpInput($pagination));
        }

        return HTMLTag::create('tr')
            ->setContent($td);
    }

    protected function createPreviousLink(GridPagination $pagination): HTMLTag
    {
        if (!$pagination->hasPreviousPage()) {
            return HTMLTag::create('span')
                ->addClass('disabled')
                ->setContent('Previous');
        }

        return HTMLTag::create('a')
            ->attr('href', $pagination->getPreviousPageURL())
            ->setContent('Previous');
    }

    protected function createNextLink(GridPagination $pagination): HTMLTag
    {
        if (!$pagination->hasNextPage()) {
            return HTMLTag::create('span')
                ->addClass('disabled')
                ->setContent('Next');
        }

        return HTMLTag::create('a')
            ->attr('href', $pagination->getNextPageURL())
            ->setContent('Next');
    }

    protected function createPageLink(int $page, string $url, bool $isCurrent): HTMLTag
    {
        if ($isCurrent) {
            return HTMLTag::create('span')
                ->addClass('current-page')
                ->setContent((string)$page);
        }

        return HTMLTag::create('a')
            ->attr('href', $url)
            ->setContent((string)$page);
    }

    protected function createEllipsis(): HTMLTag
    {
        return HTMLTag::create('span')
            ->addClass('pagination-ellipsis')
            ->setContent('…');
    }

    protected function createPageJumpInput(GridPagination $pagination): HTMLTag
    {
        $gridId = $this->grid->getID();
        $inputId = 'grid-' . $gridId . '-page-jump';
        $totalPages = $pagination->getTotalPages();
        $urlTemplate = $pagination->getPageURLTemplate();
        $encodedUrlTemplate = json_encode($urlTemplate);
        $encodedInputId = json_encode($inputId);

        $js = "var p = document.getElementById({$encodedInputId}).value; window.location.href = {$encodedUrlTemplate}.replace('{PAGE}', p)";

        $input = HTMLTag::create('input')
            ->attr('type', 'number')
            ->attr('min', '1')
            ->attr('max', (string)$totalPages)
            ->id($inputId);

        $button = HTMLTag::create('button')
            ->attr('onclick', $js)
            ->setContent('Go');

        return HTMLTag::create('span')
            ->appendContent($input)
            ->appendContent($button);
    }
}
