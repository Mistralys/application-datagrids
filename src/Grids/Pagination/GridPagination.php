<?php

declare(strict_types=1);

namespace AppUtils\Grids\Pagination;

use AppUtils\Grids\DataGrid;
use AppUtils\Grids\DataGridException;
use AppUtils\Grids\DataGridInterface;

class GridPagination
{
    /**
     * Numeric sentinel used to extract a URL template from the provider.
     *
     * This 12-digit integer is passed to {@see PaginationInterface::getPageURL()}
     * and then replaced with the literal placeholder `{PAGE}` in the result.
     * Using 12 digits (vs. the original 9) makes accidental collisions with
     * real URL parameters virtually impossible.
     */
    public const PAGE_SENTINEL = 999_999_999_999;

    private ?PaginationInterface $provider = null;
    private int $adjacentCount = 2;
    private int $edgeCount = 2;
    private bool $pageJumpEnabled = true;

    private DataGridInterface $grid;

    public function __construct(DataGridInterface $grid)
    {
        $this->grid = $grid;
    }

    public function getGrid(): DataGridInterface
    {
        return $this->grid;
    }

    // -------------------------------------------------------------------------
    // Provider management
    // -------------------------------------------------------------------------

    public function setProvider(PaginationInterface $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function getProvider(): PaginationInterface
    {
        if ($this->provider === null) {
            throw new DataGridException(
                'No pagination provider has been set. Call setProvider() first.',
                null,
                DataGridException::ERROR_NO_PAGINATION_PROVIDER
            );
        }

        return $this->provider;
    }

    public function hasProvider(): bool
    {
        return $this->provider !== null;
    }

    // -------------------------------------------------------------------------
    // Computed properties
    // -------------------------------------------------------------------------

    /**
     * Returns the total number of pages.
     * Returns 0 when there are no items.
     */
    public function getTotalPages(): int
    {
        $provider = $this->getProvider();
        $total = $provider->getTotalItems();

        if ($total === 0) {
            return 0;
        }

        return (int)ceil($total / max(1, $provider->getItemsPerPage()));
    }

    /**
     * Returns the current page number, clamped to [1, getTotalPages()].
     * Returns 1 when there are no pages.
     */
    public function getCurrentPage(): int
    {
        $totalPages = $this->getTotalPages();

        if ($totalPages === 0) {
            return 1;
        }

        return min(max(1, $this->getProvider()->getCurrentPage()), $totalPages);
    }

    public function hasPreviousPage(): bool
    {
        return $this->getCurrentPage() > 1;
    }

    public function hasNextPage(): bool
    {
        return $this->getCurrentPage() < $this->getTotalPages();
    }

    public function getPreviousPageURL(): string
    {
        return $this->getPageURL($this->getCurrentPage() - 1);
    }

    public function getNextPageURL(): string
    {
        return $this->getPageURL($this->getCurrentPage() + 1);
    }

    public function getPageURL(int $page): string
    {
        return $this->getProvider()->getPageURL($page);
    }

    // -------------------------------------------------------------------------
    // Adjacent / edge controls
    // -------------------------------------------------------------------------

    public function setAdjacentCount(int $count): self
    {
        $this->adjacentCount = $count;
        return $this;
    }

    public function setEdgeCount(int $count): self
    {
        $this->edgeCount = $count;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Page number range
    // -------------------------------------------------------------------------

    /**
     * Returns an array of page numbers with null sentinels (ellipsis markers)
     * inserted where consecutive page numbers have a gap > 1.
     *
     * Example (total=100 pages, current=50, adjacent=2, edge=2):
     *   [1, 2, null, 48, 49, 50, 51, 52, null, 99, 100]
     *
     * @return array<int|null>
     */
    public function getPageNumbers(): array
    {
        $totalPages = $this->getTotalPages();

        if ($totalPages === 0) {
            return [];
        }

        $current = $this->getCurrentPage();
        $pages = [];

        // Edge start
        for ($i = 1; $i <= min($this->edgeCount, $totalPages); $i++) {
            $pages[] = $i;
        }

        // Edge end
        for ($i = max(1, $totalPages - $this->edgeCount + 1); $i <= $totalPages; $i++) {
            $pages[] = $i;
        }

        // Window around current page
        for ($i = max(1, $current - $this->adjacentCount); $i <= min($totalPages, $current + $this->adjacentCount); $i++) {
            $pages[] = $i;
        }

        // Deduplicate and sort
        $pages = array_unique($pages);
        sort($pages);

        // Insert null sentinels for gaps
        $result = [];
        $prev = null;

        foreach ($pages as $page) {
            if ($prev !== null && ($page - $prev) > 1) {
                $result[] = null;
            }
            $result[] = $page;
            $prev = $page;
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Jump-to-page URL template
    // -------------------------------------------------------------------------

    public function isPageJumpEnabled(): bool
    {
        return $this->pageJumpEnabled;
    }

    public function setPageJumpEnabled(bool $enabled): self
    {
        $this->pageJumpEnabled = $enabled;
        return $this;
    }

    /**
     * Returns a URL template with the sentinel page number replaced
     * by the literal placeholder string `{PAGE}`.
     */
    public function getPageURLTemplate(): string
    {
        $url = $this->getProvider()->getPageURL(self::PAGE_SENTINEL);
        return str_replace((string)self::PAGE_SENTINEL, '{PAGE}', $url);
    }
}
