<?php

declare(strict_types=1);

namespace AppUtils\Grids\Renderer\Types;

use AppUtils\Grids\Pagination\GridPagination;
use AppUtils\Grids\Renderer\BaseGridRenderer;
use AppUtils\Grids\Rows\GridRowInterface;
use AppUtils\HTMLTag;
use AppUtils\Interfaces\StringableInterface;

class Bootstrap5Renderer extends BaseGridRenderer
{
    protected function init(): void
    {
        $this->grid->addClass('table');
    }

    public function makeStriped() : self
    {
        $this->grid->addClass('table-striped');
        return $this;
    }

    public function makeHover() : self
    {
        $this->grid->addClass('table-hover');
        return $this;
    }

    public function makeBordered() : self
    {
        $this->grid->addClass('table-bordered');
        return $this;
    }

    public function makeCompact() : self
    {
        $this->grid->addClass('table-sm');
        return $this;
    }

    public function renderCustomRow(GridRowInterface $row, array $columns): string
    {
        return '';
    }

    // =========================================================================
    // Bootstrap 5 Pagination (WP-005)
    // =========================================================================

    public function renderPaginationRow(GridPagination $pagination): string|StringableInterface
    {
        if (!$pagination->hasProvider() || $pagination->getTotalPages() <= 1) {
            return '';
        }

        return $this->createBootstrapPaginationRow($pagination);
    }

    private function createBootstrapPaginationRow(GridPagination $pagination): HTMLTag
    {
        $ul = HTMLTag::create('ul')
            ->addClass('pagination');

        $ul->appendContent($this->createBootstrapPreviousItem($pagination));

        foreach ($pagination->getPageNumbers() as $page) {
            if ($page === null) {
                $ul->appendContent($this->createBootstrapEllipsisItem());
            } else {
                $isCurrent = $page === $pagination->getCurrentPage();
                $url = $isCurrent ? '' : $pagination->getPageURL($page);
                $ul->appendContent($this->createBootstrapPageItem($page, $url, $isCurrent));
            }
        }

        $ul->appendContent($this->createBootstrapNextItem($pagination));

        $nav = HTMLTag::create('nav')
            ->attr('aria-label', 'Page navigation')
            ->setContent($ul);

        $td = HTMLTag::create('td')
            ->attr('colspan', (string)$this->getColspan())
            ->appendContent($nav);

        if ($pagination->isPageJumpEnabled()) {
            $td->appendContent($this->createBootstrapPageJumpInput($pagination));
        }

        return HTMLTag::create('tr')
            ->setContent($td);
    }

    private function createBootstrapPreviousItem(GridPagination $pagination): HTMLTag
    {
        if (!$pagination->hasPreviousPage()) {
            $link = HTMLTag::create('span')
                ->addClass('page-link')
                ->attr('aria-label', 'Previous page')
                ->setContent('&laquo;');

            return HTMLTag::create('li')
                ->addClasses(['page-item', 'disabled'])
                ->setContent($link);
        }

        $link = HTMLTag::create('a')
            ->addClass('page-link')
            ->attr('href', $pagination->getPreviousPageURL())
            ->attr('aria-label', 'Previous page')
            ->setContent('&laquo;');

        return HTMLTag::create('li')
            ->addClass('page-item')
            ->setContent($link);
    }

    private function createBootstrapNextItem(GridPagination $pagination): HTMLTag
    {
        if (!$pagination->hasNextPage()) {
            $link = HTMLTag::create('span')
                ->addClass('page-link')
                ->attr('aria-label', 'Next page')
                ->setContent('&raquo;');

            return HTMLTag::create('li')
                ->addClasses(['page-item', 'disabled'])
                ->setContent($link);
        }

        $link = HTMLTag::create('a')
            ->addClass('page-link')
            ->attr('href', $pagination->getNextPageURL())
            ->attr('aria-label', 'Next page')
            ->setContent('&raquo;');

        return HTMLTag::create('li')
            ->addClass('page-item')
            ->setContent($link);
    }

    private function createBootstrapPageItem(int $page, string $url, bool $isCurrent): HTMLTag
    {
        if ($isCurrent) {
            $link = HTMLTag::create('span')
                ->addClass('page-link')
                ->setContent((string)$page);

            return HTMLTag::create('li')
                ->addClasses(['page-item', 'active'])
                ->attr('aria-current', 'page')
                ->setContent($link);
        }

        $link = HTMLTag::create('a')
            ->addClass('page-link')
            ->attr('href', $url)
            ->setContent((string)$page);

        return HTMLTag::create('li')
            ->addClass('page-item')
            ->setContent($link);
    }

    private function createBootstrapEllipsisItem(): HTMLTag
    {
        $span = HTMLTag::create('span')
            ->addClass('page-link')
            ->setContent('&hellip;');

        return HTMLTag::create('li')
            ->addClasses(['page-item', 'disabled'])
            ->setContent($span);
    }

    private function createBootstrapPageJumpInput(GridPagination $pagination): HTMLTag
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
            ->addClasses(['form-control', 'form-control-sm'])
            ->attr('style', 'width:80px')
            ->attr('min', '1')
            ->attr('max', (string)$totalPages)
            ->id($inputId);

        $button = HTMLTag::create('button')
            ->addClasses(['btn', 'btn-sm', 'btn-outline-secondary'])
            ->attr('onclick', $js)
            ->setContent('Go');

        return HTMLTag::create('div')
            ->addClasses(['d-flex', 'align-items-center', 'gap-2', 'mt-2'])
            ->appendContent($input)
            ->appendContent($button);
    }
}
