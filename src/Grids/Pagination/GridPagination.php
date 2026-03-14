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

    /**
     * Numeric sentinel used to extract an items-per-page URL template.
     * Replaced with the `{IPP}` placeholder in the resulting template string.
     */
    public const IPP_SENTINEL = 888_888_888_888;

    private ?PaginationInterface $provider = null;
    private int $adjacentCount = 2;
    private int $edgeCount = 2;
    private bool $pageJumpEnabled = true;
    private bool $showAtTop = false;

    /** @var int[] */
    private array $itemsPerPageOptions = [];
    private int $defaultItemsPerPage = 25;
    private string $ippParam = 'ipp';
    private ?int $resolvedItemsPerPage = null;

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

    // -------------------------------------------------------------------------
    // Show at top
    // -------------------------------------------------------------------------

    public function isShowAtTop(): bool
    {
        return $this->showAtTop;
    }

    public function setShowAtTop(bool $showAtTop = true): self
    {
        $this->showAtTop = $showAtTop;
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

    // -------------------------------------------------------------------------
    // Items-per-page options
    // -------------------------------------------------------------------------

    /**
     * Sets the available items-per-page choices.
     * Values are deduplicated, filtered to positive integers, and sorted ascending.
     *
     * @param int[] $options
     */
    public function setItemsPerPageOptions(array $options): self
    {
        $filtered = array_filter($options, static fn(int $v): bool => $v > 0);
        $unique = array_unique($filtered);
        sort($unique);
        $this->itemsPerPageOptions = $unique;
        return $this;
    }

    /**
     * Returns the configured items-per-page choices.
     *
     * @return int[]
     */
    public function getItemsPerPageOptions(): array
    {
        return $this->itemsPerPageOptions;
    }

    public function hasItemsPerPageOptions(): bool
    {
        return !empty($this->itemsPerPageOptions);
    }

    public function setDefaultItemsPerPage(int $default): self
    {
        $this->defaultItemsPerPage = $default;
        return $this;
    }

    public function getDefaultItemsPerPage(): int
    {
        return $this->defaultItemsPerPage;
    }

    public function setItemsPerPageParam(string $param): self
    {
        $this->ippParam = $param;
        return $this;
    }

    public function getItemsPerPageParam(): string
    {
        return $this->ippParam;
    }

    // -------------------------------------------------------------------------
    // Items-per-page resolution (lazy, cached)
    // -------------------------------------------------------------------------

    /**
     * Resolves the effective items-per-page value using the priority chain:
     * $_GET[param] → GridSettings → $default (or configured default).
     *
     * When a valid $_GET value is found it is persisted to GridSettings
     * automatically. The result is cached for the lifetime of this object.
     */
    public function resolveItemsPerPage(?int $default = null): int
    {
        if ($this->resolvedItemsPerPage !== null) {
            return $this->resolvedItemsPerPage;
        }

        $fallback = $default ?? $this->defaultItemsPerPage;

        // Priority 1: valid $_GET value
        if (isset($_GET[$this->ippParam])) {
            $value = (int)$_GET[$this->ippParam];
            if (in_array($value, $this->itemsPerPageOptions, true)) {
                $this->grid->settings()->setItemsPerPage($value);
                $this->resolvedItemsPerPage = $value;
                return $this->resolvedItemsPerPage;
            }
        }

        // Priority 2: GridSettings (falls back to $fallback if nothing stored)
        $this->resolvedItemsPerPage = $this->grid->settings()->getItemsPerPage($fallback) ?? $fallback;
        return $this->resolvedItemsPerPage;
    }

    /**
     * Returns the effective items-per-page value (alias for resolveItemsPerPage).
     * Intended for use in the renderer after resolution has already occurred.
     */
    public function getEffectiveItemsPerPage(): int
    {
        return $this->resolveItemsPerPage();
    }

    // -------------------------------------------------------------------------
    // Items-per-page URL building
    // -------------------------------------------------------------------------

    /**
     * Returns a URL for the given items-per-page value, resetting to page 1.
     */
    public function getItemsPerPageURL(int $itemsPerPage): string
    {
        $url = $this->getProvider()->getPageURL(1);
        $parts = parse_url($url);
        $query = isset($parts['query']) ? (string)$parts['query'] : '';

        parse_str($query, $params);
        $params[$this->ippParam] = $itemsPerPage;
        $qs = http_build_query($params);

        $path = isset($parts['path']) ? (string)$parts['path'] : '/';
        return $path . ($qs !== '' ? '?' . $qs : '');
    }

    /**
     * Returns a URL template with the items-per-page sentinel replaced
     * by the literal placeholder string `{IPP}`.
     */
    public function getItemsPerPageURLTemplate(): string
    {
        $url = $this->getItemsPerPageURL(self::IPP_SENTINEL);
        return str_replace((string)self::IPP_SENTINEL, '{IPP}', $url);
    }
}
