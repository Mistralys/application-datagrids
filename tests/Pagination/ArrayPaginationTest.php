<?php

declare(strict_types=1);

namespace AppUtils\Tests\Pagination;

use AppUtils\Grids\Pagination\Types\ArrayPagination;
use PHPUnit\Framework\TestCase;

class ArrayPaginationTest extends TestCase
{
    private string|null $originalRequestUri = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalRequestUri = $_SERVER['REQUEST_URI'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->originalRequestUri !== null) {
            $_SERVER['REQUEST_URI'] = $this->originalRequestUri;
        } else {
            unset($_SERVER['REQUEST_URI']);
        }
        parent::tearDown();
    }

    // =========================================================================
    // getSlicedItems() — page 1
    // =========================================================================

    public function test_getSlicedItems_firstPage(): void
    {
        $items = range(1, 100);
        $pagination = new ArrayPagination($items, 25, 1);

        $sliced = $pagination->getSlicedItems();

        $this->assertCount(25, $sliced);
        $this->assertSame(1, $sliced[0]);
        $this->assertSame(25, $sliced[24]);
    }

    // =========================================================================
    // getSlicedItems() — middle page
    // =========================================================================

    public function test_getSlicedItems_middlePage(): void
    {
        $items = range(1, 100);
        $pagination = new ArrayPagination($items, 25, 2);

        $sliced = $pagination->getSlicedItems();

        $this->assertCount(25, $sliced);
        $this->assertSame(26, $sliced[0]);
        $this->assertSame(50, $sliced[24]);
    }

    // =========================================================================
    // getSlicedItems() — last page
    // =========================================================================

    public function test_getSlicedItems_lastPage(): void
    {
        $items = range(1, 100);
        $pagination = new ArrayPagination($items, 25, 4);

        $sliced = $pagination->getSlicedItems();

        $this->assertCount(25, $sliced);
        $this->assertSame(76, $sliced[0]);
        $this->assertSame(100, $sliced[24]);
    }

    // =========================================================================
    // getSlicedItems() — single-item last page
    // =========================================================================

    public function test_getSlicedItems_singleItemLastPage(): void
    {
        $items = range(1, 26);
        $pagination = new ArrayPagination($items, 25, 2);

        $sliced = $pagination->getSlicedItems();

        $this->assertCount(1, $sliced);
        $this->assertSame(26, $sliced[0]);
    }

    // =========================================================================
    // getPageURL() — adds page param when absent
    // =========================================================================

    public function test_getPageURL_addsPageParam(): void
    {
        $_SERVER['REQUEST_URI'] = '/items';
        $pagination = new ArrayPagination(range(1, 100), 25, 1);

        $url = $pagination->getPageURL(3);

        $this->assertSame('/items?page=3', $url);
    }

    // =========================================================================
    // getPageURL() — replaces page param when present
    // =========================================================================

    public function test_getPageURL_replacesPageParam(): void
    {
        $_SERVER['REQUEST_URI'] = '/items?page=1&sort=name';
        $pagination = new ArrayPagination(range(1, 100), 25, 1);

        $url = $pagination->getPageURL(5);

        $this->assertStringContainsString('page=5', $url);
        $this->assertStringContainsString('sort=name', $url);
        $this->assertStringNotContainsString('page=1', $url);
    }

    // =========================================================================
    // getPageURL() — custom page param
    // =========================================================================

    public function test_getPageURL_customPageParam(): void
    {
        $_SERVER['REQUEST_URI'] = '/items?p=2';
        $pagination = new ArrayPagination(range(1, 100), 25, 1, 'p');

        $url = $pagination->getPageURL(3);

        $this->assertStringContainsString('p=3', $url);
    }

    // =========================================================================
    // getTotalItems() / getItemsPerPage() / getCurrentPage()
    // =========================================================================

    public function test_totalItems(): void
    {
        $pagination = new ArrayPagination(range(1, 42), 10, 1);
        $this->assertSame(42, $pagination->getTotalItems());
    }

    public function test_itemsPerPage(): void
    {
        $pagination = new ArrayPagination(range(1, 42), 10, 1);
        $this->assertSame(10, $pagination->getItemsPerPage());
    }

    public function test_currentPage_clampedToMax(): void
    {
        // 42 items / 10 per page = 5 pages. Page 99 should clamp to 5.
        $pagination = new ArrayPagination(range(1, 42), 10, 99);
        $this->assertSame(5, $pagination->getCurrentPage());
    }

    public function test_currentPage_clampedToMin(): void
    {
        $pagination = new ArrayPagination(range(1, 42), 10, 0);
        $this->assertSame(1, $pagination->getCurrentPage());
    }
}
