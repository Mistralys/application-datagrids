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
