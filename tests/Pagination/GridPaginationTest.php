<?php

declare(strict_types=1);

namespace AppUtils\Tests\Pagination;

use AppUtils\Grids\DataGrid;
use AppUtils\Grids\Pagination\GridPagination;
use AppUtils\Grids\Pagination\PaginationInterface;
use AppUtils\Tests\TestClasses\InMemoryStorage;
use PHPUnit\Framework\TestCase;

class GridPaginationTest extends TestCase
{
    // =========================================================================
    // getTotalPages()
    // =========================================================================

    public function test_getTotalPages_exactDivision(): void
    {
        $pagination = $this->createPagination(100, 25, 1);
        $this->assertSame(4, $pagination->getTotalPages());
    }

    public function test_getTotalPages_remainder(): void
    {
        $pagination = $this->createPagination(101, 25, 1);
        $this->assertSame(5, $pagination->getTotalPages());
    }

    public function test_getTotalPages_zeroItems(): void
    {
        $pagination = $this->createPagination(0, 25, 1);
        $this->assertSame(0, $pagination->getTotalPages());
    }

    public function test_getTotalPages_singleItem(): void
    {
        $pagination = $this->createPagination(1, 25, 1);
        $this->assertSame(1, $pagination->getTotalPages());
    }

    public function test_getTotalPages_itemsEqualPerPage(): void
    {
        $pagination = $this->createPagination(25, 25, 1);
        $this->assertSame(1, $pagination->getTotalPages());
    }

    // =========================================================================
    // getCurrentPage() clamping
    // =========================================================================

    public function test_getCurrentPage_clampsBelowMin(): void
    {
        $pagination = $this->createPagination(100, 25, 0);
        $this->assertSame(1, $pagination->getCurrentPage());
    }

    public function test_getCurrentPage_clampsAboveMax(): void
    {
        $pagination = $this->createPagination(100, 25, 999);
        $this->assertSame(4, $pagination->getCurrentPage());
    }

    public function test_getCurrentPage_withinRange(): void
    {
        $pagination = $this->createPagination(100, 25, 3);
        $this->assertSame(3, $pagination->getCurrentPage());
    }

    public function test_getCurrentPage_zeroItemsReturnsOne(): void
    {
        $pagination = $this->createPagination(0, 25, 1);
        $this->assertSame(1, $pagination->getCurrentPage());
    }

    // =========================================================================
    // getPageNumbers()
    // =========================================================================

    public function test_getPageNumbers_smallPageCount_noEllipsis(): void
    {
        // 7 pages, all should be listed with no nulls
        $pagination = $this->createPagination(175, 25, 4);
        $numbers = $pagination->getPageNumbers();

        $this->assertSame([1, 2, 3, 4, 5, 6, 7], $numbers);
    }

    public function test_getPageNumbers_largePage_twoEllipsis(): void
    {
        // 100 pages, current = 50, adjacent=2, edge=2
        $pagination = $this->createPagination(2500, 25, 50);
        $numbers = $pagination->getPageNumbers();

        // Expected: [1, 2, null, 48, 49, 50, 51, 52, null, 99, 100]
        $this->assertSame([1, 2, null, 48, 49, 50, 51, 52, null, 99, 100], $numbers);
    }

    public function test_getPageNumbers_currentIsFirstPage(): void
    {
        $pagination = $this->createPagination(2500, 25, 1);
        $numbers = $pagination->getPageNumbers();

        // Current=1, adjacent=2, edge=2 → pages 1,2,3 from adjacent + 1,2 from edge start + 99,100 from edge end
        // Deduplicated + sorted: [1, 2, 3, null, 99, 100]
        $this->assertSame([1, 2, 3, null, 99, 100], $numbers);
    }

    public function test_getPageNumbers_currentIsLastPage(): void
    {
        $pagination = $this->createPagination(2500, 25, 100);
        $numbers = $pagination->getPageNumbers();

        // Current=100, adjacent=2, edge=2 → 1,2 from edge start + 98,99,100 from adjacent + 99,100 from edge end
        // Deduplicated + sorted: [1, 2, null, 98, 99, 100]
        $this->assertSame([1, 2, null, 98, 99, 100], $numbers);
    }

    public function test_getPageNumbers_zeroItems(): void
    {
        $pagination = $this->createPagination(0, 25, 1);
        $this->assertSame([], $pagination->getPageNumbers());
    }

    public function test_getPageNumbers_singlePage(): void
    {
        $pagination = $this->createPagination(10, 25, 1);
        $this->assertSame([1], $pagination->getPageNumbers());
    }

    public function test_getPageNumbers_nullEntriesAreEllipsis(): void
    {
        $pagination = $this->createPagination(2500, 25, 50);
        $numbers = $pagination->getPageNumbers();

        $nullCount = count(array_filter($numbers, static fn($v) => $v === null));
        $this->assertSame(2, $nullCount, 'Expected exactly 2 ellipsis sentinels');
    }

    // =========================================================================
    // hasPreviousPage() / hasNextPage()
    // =========================================================================

    public function test_hasPreviousPage_firstPage(): void
    {
        $pagination = $this->createPagination(100, 25, 1);
        $this->assertFalse($pagination->hasPreviousPage());
    }

    public function test_hasPreviousPage_middlePage(): void
    {
        $pagination = $this->createPagination(100, 25, 2);
        $this->assertTrue($pagination->hasPreviousPage());
    }

    public function test_hasNextPage_lastPage(): void
    {
        $pagination = $this->createPagination(100, 25, 4);
        $this->assertFalse($pagination->hasNextPage());
    }

    public function test_hasNextPage_middlePage(): void
    {
        $pagination = $this->createPagination(100, 25, 2);
        $this->assertTrue($pagination->hasNextPage());
    }

    public function test_hasBothPrevAndNext_middlePage(): void
    {
        $pagination = $this->createPagination(100, 25, 2);
        $this->assertTrue($pagination->hasPreviousPage());
        $this->assertTrue($pagination->hasNextPage());
    }

    // =========================================================================
    // getPageURLTemplate()
    // =========================================================================

    public function test_getPageURLTemplate_containsPlaceholder(): void
    {
        $pagination = $this->createPagination(100, 25, 1);
        $template = $pagination->getPageURLTemplate();

        $this->assertStringContainsString('{PAGE}', $template);
        // Ensure no raw numeric sentinel leaks through
        $this->assertStringNotContainsString('999999999999', $template);
    }

    // =========================================================================
    // IPP — setItemsPerPageOptions / getItemsPerPageOptions / hasItemsPerPageOptions
    // =========================================================================

    public function test_setItemsPerPageOptions_sortsDedupesFilters(): void
    {
        // Duplicates removed, sorted ascending, non-positive filtered out
        $pagination = $this->createPagination(100, 25, 1);
        $pagination->setItemsPerPageOptions([50, 10, 20, 10, 0, -5]);

        $this->assertSame([10, 20, 50], $pagination->getItemsPerPageOptions());
    }

    public function test_hasItemsPerPageOptions_falseByDefault(): void
    {
        $pagination = $this->createPagination(100, 25, 1);

        $this->assertFalse($pagination->hasItemsPerPageOptions());
    }

    public function test_hasItemsPerPageOptions_trueAfterConfiguration(): void
    {
        $pagination = $this->createPagination(100, 25, 1);
        $pagination->setItemsPerPageOptions([10, 20, 50]);

        $this->assertTrue($pagination->hasItemsPerPageOptions());
    }

    // =========================================================================
    // IPP — resolveItemsPerPage priority chain
    // =========================================================================

    public function test_resolveItemsPerPage_usesPassedDefaultWhenNothingStored(): void
    {
        $pagination = $this->createPagination(100, 25, 1);
        $pagination->setItemsPerPageOptions([10, 20, 50]);

        $result = $pagination->resolveItemsPerPage(20);

        $this->assertSame(20, $result);
    }

    public function test_resolveItemsPerPage_usesGridSettingsOverDefault(): void
    {
        $grid = DataGrid::create('test-ipp', new InMemoryStorage());
        $grid->pagination()->setProvider($this->createStubProvider(100, 25, 1));
        $grid->pagination()->setItemsPerPageOptions([10, 20, 50]);

        // Pre-store a value in GridSettings
        $grid->settings()->setItemsPerPage(50);

        $result = $grid->pagination()->resolveItemsPerPage(10);

        $this->assertSame(50, $result);
    }

    public function test_resolveItemsPerPage_usesGetOverGridSettings(): void
    {
        $grid = DataGrid::create('test-ipp-get', new InMemoryStorage());
        $grid->pagination()->setProvider($this->createStubProvider(100, 25, 1));
        $grid->pagination()->setItemsPerPageOptions([10, 20, 50]);
        $grid->settings()->setItemsPerPage(10);

        // Simulate valid $_GET value (in options)
        $_GET['ipp'] = '20';
        try {
            $result = $grid->pagination()->resolveItemsPerPage(10);
        } finally {
            unset($_GET['ipp']);
        }

        $this->assertSame(20, $result);
    }

    // =========================================================================
    // IPP — valid $_GET value is persisted to GridSettings
    // =========================================================================

    public function test_resolveItemsPerPage_persistsValidGetValueToSettings(): void
    {
        $grid = DataGrid::create('test-ipp-persist', new InMemoryStorage());
        $grid->pagination()->setProvider($this->createStubProvider(100, 25, 1));
        $grid->pagination()->setItemsPerPageOptions([10, 20, 50]);

        $this->assertNull($grid->settings()->getItemsPerPage());

        $_GET['ipp'] = '50';
        try {
            $grid->pagination()->resolveItemsPerPage(10);
        } finally {
            unset($_GET['ipp']);
        }

        $this->assertSame(50, $grid->settings()->getItemsPerPage());
    }

    // =========================================================================
    // IPP — invalid $_GET value (not in options) is ignored
    // =========================================================================

    public function test_resolveItemsPerPage_ignoresInvalidGetValue(): void
    {
        $grid = DataGrid::create('test-ipp-invalid', new InMemoryStorage());
        $grid->pagination()->setProvider($this->createStubProvider(100, 25, 1));
        $grid->pagination()->setItemsPerPageOptions([10, 20, 50]);

        $_GET['ipp'] = '999'; // not in options
        try {
            $result = $grid->pagination()->resolveItemsPerPage(25);
        } finally {
            unset($_GET['ipp']);
        }

        $this->assertSame(25, $result);
    }

    // =========================================================================
    // IPP — resolveItemsPerPage caches result (idempotent)
    // =========================================================================

    public function test_resolveItemsPerPage_isIdempotent(): void
    {
        $pagination = $this->createPagination(100, 25, 1);
        $pagination->setItemsPerPageOptions([10, 20, 50]);

        $first  = $pagination->resolveItemsPerPage(10);
        $second = $pagination->resolveItemsPerPage(10);

        $this->assertSame($first, $second);
    }

    public function test_resolveItemsPerPage_cacheIgnoresSubsequentDefault(): void
    {
        $pagination = $this->createPagination(100, 25, 1);
        $pagination->setItemsPerPageOptions([10, 20, 50]);

        $first  = $pagination->resolveItemsPerPage(10);
        // Second call with different default — should still return the cached value
        $second = $pagination->resolveItemsPerPage(50);

        $this->assertSame($first, $second);
    }

    // =========================================================================
    // IPP — getItemsPerPageURL resets to page 1
    // =========================================================================

    public function test_getItemsPerPageURL_resetsToPageOne(): void
    {
        // Provider returns /items?page=N — start on page 3
        $pagination = $this->createPagination(100, 25, 3);

        $url = $pagination->getItemsPerPageURL(20);

        $this->assertStringContainsString('page=1', $url);
        $this->assertStringNotContainsString('page=3', $url);
        $this->assertStringContainsString('ipp=20', $url);
    }

    // =========================================================================
    // IPP — getItemsPerPageURLTemplate
    // =========================================================================

    public function test_getItemsPerPageURLTemplate_containsPlaceholder(): void
    {
        $pagination = $this->createPagination(100, 25, 1);
        $template = $pagination->getItemsPerPageURLTemplate();

        $this->assertStringContainsString('{IPP}', $template);
        // Sentinel must not leak through
        $this->assertStringNotContainsString('888888888888', $template);
    }

    // =========================================================================
    // IPP — setItemsPerPageParam customises the GET parameter name
    // =========================================================================

    public function test_setItemsPerPageParam_usesCustomParamForResolution(): void
    {
        $grid = DataGrid::create('test-ipp-param', new InMemoryStorage());
        $grid->pagination()->setProvider($this->createStubProvider(100, 25, 1));
        $grid->pagination()->setItemsPerPageOptions([10, 20, 50]);
        $grid->pagination()->setItemsPerPageParam('per_page');

        $this->assertSame('per_page', $grid->pagination()->getItemsPerPageParam());

        // Only the custom param should be honoured — default 'ipp' must be ignored
        $_GET['per_page'] = '20';
        try {
            $result = $grid->pagination()->resolveItemsPerPage(10);
        } finally {
            unset($_GET['per_page']);
        }

        $this->assertSame(20, $result);
    }

    public function test_setItemsPerPageParam_ignoresOldDefaultParam(): void
    {
        $grid = DataGrid::create('test-ipp-param2', new InMemoryStorage());
        $grid->pagination()->setProvider($this->createStubProvider(100, 25, 1));
        $grid->pagination()->setItemsPerPageOptions([10, 20, 50]);
        $grid->pagination()->setItemsPerPageParam('per_page');

        // Set the old 'ipp' key — should be ignored
        $_GET['ipp'] = '50';
        try {
            $result = $grid->pagination()->resolveItemsPerPage(10);
        } finally {
            unset($_GET['ipp']);
        }

        // Falls through to default because 'per_page' is not in $_GET
        $this->assertSame(10, $result);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createPagination(int $totalItems, int $itemsPerPage, int $currentPage): GridPagination
    {
        $provider = $this->createStubProvider($totalItems, $itemsPerPage, $currentPage);

        $grid = DataGrid::create('test-grid', new InMemoryStorage());
        $grid->pagination()->setProvider($provider);

        return $grid->pagination();
    }

    private function createStubProvider(int $totalItems, int $itemsPerPage, int $currentPage): PaginationInterface
    {
        return new class($totalItems, $itemsPerPage, $currentPage) implements PaginationInterface {
            public function __construct(
                private int $totalItems,
                private int $itemsPerPage,
                private int $currentPage
            ) {}

            public function getTotalItems(): int { return $this->totalItems; }
            public function getItemsPerPage(): int { return $this->itemsPerPage; }
            public function getCurrentPage(): int { return $this->currentPage; }
            public function getPageURL(int $page): string { return '/items?page=' . $page; }
        };
    }
}
