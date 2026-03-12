<?php

declare(strict_types=1);

namespace AppUtils\Grids\Pagination\Types;

use AppUtils\Grids\Pagination\PaginationInterface;

/**
 * Array-backed pagination provider.
 *
 * Accepts a full array of items and slices it to the requested page.
 * If $currentPage is null it is resolved from $_GET[$pageParam] (default: 'page').
 * The resolved current page is clamped to the valid range [1, totalPages].
 */
class ArrayPagination implements PaginationInterface
{
    private int $currentPage;

    /**
     * @param array<mixed> $items        Full list of items to paginate.
     * @param int          $itemsPerPage Items shown per page (default: 25).
     * @param int|null     $currentPage  Explicit page number, or null to read from $_GET.
     * @param string       $pageParam    Query-string parameter name used for page numbers.
     */
    public function __construct(
        private array $items,
        private int $itemsPerPage = 25,
        ?int $currentPage = null,
        private string $pageParam = 'page'
    ) {
        if ($currentPage === null) {
            $currentPage = isset($_GET[$this->pageParam]) ? (int)$_GET[$this->pageParam] : 1;
        }

        $totalPages = $this->computeTotalPages();
        $this->currentPage = min(max(1, $currentPage), max(1, $totalPages));
    }

    // -------------------------------------------------------------------------
    // PaginationInterface
    // -------------------------------------------------------------------------

    public function getTotalItems(): int
    {
        return count($this->items);
    }

    public function getItemsPerPage(): int
    {
        return $this->itemsPerPage;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * Builds a URL for the given page number by rewriting or adding the page
     * parameter in the current request URI's query string.
     */
    public function getPageURL(int $page): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $parts = parse_url($uri);
        $path = isset($parts['path']) ? (string)$parts['path'] : '/';
        $query = isset($parts['query']) ? (string)$parts['query'] : '';

        parse_str($query, $params);
        $params[$this->pageParam] = $page;
        $qs = http_build_query($params);

        return $path . ($qs !== '' ? '?' . $qs : '');
    }

    // -------------------------------------------------------------------------
    // Array slicing
    // -------------------------------------------------------------------------

    /**
     * Returns the slice of $items for the current page.
     *
     * @return array<mixed>
     */
    public function getSlicedItems(): array
    {
        $offset = ($this->getCurrentPage() - 1) * max(1, $this->itemsPerPage);
        return array_slice($this->items, $offset, max(1, $this->itemsPerPage));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function getPageParameterName(): string
    {
        return $this->pageParam;
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private function computeTotalPages(): int
    {
        $total = count($this->items);

        if ($total === 0) {
            return 0;
        }

        return (int)ceil($total / max(1, $this->itemsPerPage));
    }
}
