<?php

declare(strict_types=1);

namespace AppUtils\Tests\Sorting;

use AppUtils\Grids\DataGrid;
use AppUtils\Grids\DataGridInterface;
use AppUtils\Grids\Rows\Types\MergedRow;
use PHPUnit\Framework\TestCase;
use AppUtils\Grids\Rows\Types\StandardRow;

/**
 * Tests for SortManager::sortRows() covering all sort modes,
 * both directions, edge cases, and MergedRow position preservation.
 */
class SortManagerTest extends TestCase
{
    /**
     * @var array<string,mixed>
     */
    private array $originalGet = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalGet = $_GET;
    }

    protected function tearDown(): void
    {
        $_GET = $this->originalGet;
        parent::tearDown();
    }

    // =========================================================================
    // Native mode — string sort
    // =========================================================================

    public function test_sortRows_nativeAsc(): void
    {
        $_GET['sort'] = 'name';
        unset($_GET['sort_dir']);

        $grid = $this->createGrid();
        $grid->columns()->getByName('name')->useNativeSorting();

        $grid->rows()->addArray(['id' => '3', 'name' => 'Charlie']);
        $grid->rows()->addArray(['id' => '1', 'name' => 'Alice']);
        $grid->rows()->addArray(['id' => '2', 'name' => 'Bob']);

        $rows = $grid->rows()->getRows();
        $grid->sorting()->sortRows($rows);

        $this->assertInstanceOf(StandardRow::class, $rows[0]);
        $this->assertInstanceOf(StandardRow::class, $rows[1]);
        $this->assertInstanceOf(StandardRow::class, $rows[2]);
        $this->assertSame('Alice', $rows[0]->getCell('name')->getValue());
        $this->assertSame('Bob', $rows[1]->getCell('name')->getValue());
        $this->assertSame('Charlie', $rows[2]->getCell('name')->getValue());
    }

    public function test_sortRows_nativeDesc(): void
    {
        $_GET['sort'] = 'name';
        $_GET['sort_dir'] = 'DESC';

        $grid = $this->createGrid();
        $grid->columns()->getByName('name')->useNativeSorting();

        $grid->rows()->addArray(['id' => '1', 'name' => 'Alice']);
        $grid->rows()->addArray(['id' => '2', 'name' => 'Bob']);
        $grid->rows()->addArray(['id' => '3', 'name' => 'Charlie']);

        $rows = $grid->rows()->getRows();
        $grid->sorting()->sortRows($rows);

        $this->assertInstanceOf(StandardRow::class, $rows[0]);
        $this->assertInstanceOf(StandardRow::class, $rows[1]);
        $this->assertInstanceOf(StandardRow::class, $rows[2]);
        $this->assertSame('Charlie', $rows[0]->getCell('name')->getValue());
        $this->assertSame('Bob', $rows[1]->getCell('name')->getValue());
        $this->assertSame('Alice', $rows[2]->getCell('name')->getValue());
    }

    // =========================================================================
    // Callback mode
    // =========================================================================

    public function test_sortRows_callbackAsc(): void
    {
        $_GET['sort'] = 'name';
        unset($_GET['sort_dir']);

        $grid = $this->createGrid();
        // Callback: natural alphabetical order (A < B < C)
        $grid->columns()->getByName('name')->useCallbackSorting(
            static function (StandardRow $a, StandardRow $b): int {
                return strcasecmp(
                    (string)$a->getCell('name')->getValue(),
                    (string)$b->getCell('name')->getValue()
                );
            }
        );

        $grid->rows()->addArray(['id' => '3', 'name' => 'Charlie']);
        $grid->rows()->addArray(['id' => '1', 'name' => 'Alice']);
        $grid->rows()->addArray(['id' => '2', 'name' => 'Bob']);

        $rows = $grid->rows()->getRows();
        $grid->sorting()->sortRows($rows);

        $this->assertInstanceOf(StandardRow::class, $rows[0]);
        $this->assertInstanceOf(StandardRow::class, $rows[1]);
        $this->assertInstanceOf(StandardRow::class, $rows[2]);
        $this->assertSame('Alice', $rows[0]->getCell('name')->getValue());
        $this->assertSame('Bob', $rows[1]->getCell('name')->getValue());
        $this->assertSame('Charlie', $rows[2]->getCell('name')->getValue());
    }

    public function test_sortRows_callbackDesc(): void
    {
        $_GET['sort'] = 'name';
        $_GET['sort_dir'] = DataGridInterface::SORT_DESC;

        $grid = $this->createGrid();
        // Callback: natural alphabetical order (A < B < C)
        // With DESC direction, SortManager negates the comparator result → C, B, A
        $grid->columns()->getByName('name')->useCallbackSorting(
            static function (StandardRow $a, StandardRow $b): int {
                return strcasecmp(
                    (string)$a->getCell('name')->getValue(),
                    (string)$b->getCell('name')->getValue()
                );
            }
        );

        $grid->rows()->addArray(['id' => '1', 'name' => 'Alice']);
        $grid->rows()->addArray(['id' => '2', 'name' => 'Bob']);
        $grid->rows()->addArray(['id' => '3', 'name' => 'Charlie']);

        $rows = $grid->rows()->getRows();
        $grid->sorting()->sortRows($rows);

        $this->assertInstanceOf(StandardRow::class, $rows[0]);
        $this->assertInstanceOf(StandardRow::class, $rows[1]);
        $this->assertInstanceOf(StandardRow::class, $rows[2]);
        $this->assertSame('Charlie', $rows[0]->getCell('name')->getValue());
        $this->assertSame('Bob', $rows[1]->getCell('name')->getValue());
        $this->assertSame('Alice', $rows[2]->getCell('name')->getValue());
    }

    // =========================================================================
    // Manual mode — no-op
    // =========================================================================

    public function test_sortRows_manual_noOp(): void
    {
        $_GET['sort'] = 'name';
        unset($_GET['sort_dir']);

        $grid = $this->createGrid();
        $grid->columns()->getByName('name')->useManualSorting();

        $grid->rows()->addArray(['id' => '3', 'name' => 'Charlie']);
        $grid->rows()->addArray(['id' => '1', 'name' => 'Alice']);
        $grid->rows()->addArray(['id' => '2', 'name' => 'Bob']);

        $rows = $grid->rows()->getRows();
        $grid->sorting()->sortRows($rows);

        // Manual mode: rows remain in insertion order
        $this->assertInstanceOf(StandardRow::class, $rows[0]);
        $this->assertInstanceOf(StandardRow::class, $rows[1]);
        $this->assertInstanceOf(StandardRow::class, $rows[2]);
        $this->assertSame('Charlie', $rows[0]->getCell('name')->getValue());
        $this->assertSame('Alice', $rows[1]->getCell('name')->getValue());
        $this->assertSame('Bob', $rows[2]->getCell('name')->getValue());
    }

    // =========================================================================
    // No active sort column — no-op
    // =========================================================================

    public function test_sortRows_noSortColumn_noOp(): void
    {
        $_GET = [];

        $grid = $this->createGrid();
        $grid->columns()->getByName('name')->useNativeSorting();

        $grid->rows()->addArray(['id' => '3', 'name' => 'Charlie']);
        $grid->rows()->addArray(['id' => '1', 'name' => 'Alice']);
        $grid->rows()->addArray(['id' => '2', 'name' => 'Bob']);

        $rows = $grid->rows()->getRows();
        $grid->sorting()->sortRows($rows);

        // No sort param in $_GET → rows unchanged
        $this->assertInstanceOf(StandardRow::class, $rows[0]);
        $this->assertInstanceOf(StandardRow::class, $rows[1]);
        $this->assertInstanceOf(StandardRow::class, $rows[2]);
        $this->assertSame('Charlie', $rows[0]->getCell('name')->getValue());
        $this->assertSame('Alice', $rows[1]->getCell('name')->getValue());
        $this->assertSame('Bob', $rows[2]->getCell('name')->getValue());
    }

    // =========================================================================
    // MergedRow position preservation
    // =========================================================================

    public function test_sortRows_preservesMergedRowPositions(): void
    {
        $_GET['sort'] = 'name';
        unset($_GET['sort_dir']);

        $grid = $this->createGrid();
        $grid->columns()->getByName('name')->useNativeSorting();

        // Insertion order: Charlie [0], MergedRow [1], Alice [2], Bob [3]
        $grid->rows()->addArray(['id' => '3', 'name' => 'Charlie']);
        $grid->rows()->addMerged('Section Header');
        $grid->rows()->addArray(['id' => '1', 'name' => 'Alice']);
        $grid->rows()->addArray(['id' => '2', 'name' => 'Bob']);

        $rows = $grid->rows()->getRows();
        $grid->sorting()->sortRows($rows);

        // MergedRow must remain at its original index 1
        $this->assertInstanceOf(MergedRow::class, $rows[1]);

        // StandardRows at indices 0, 2, 3 must be sorted ASC
        $this->assertInstanceOf(StandardRow::class, $rows[0]);
        $this->assertInstanceOf(StandardRow::class, $rows[2]);
        $this->assertInstanceOf(StandardRow::class, $rows[3]);
        $this->assertSame('Alice', $rows[0]->getCell('name')->getValue());
        $this->assertSame('Bob', $rows[2]->getCell('name')->getValue());
        $this->assertSame('Charlie', $rows[3]->getCell('name')->getValue());
    }

    // =========================================================================
    // Numeric native sorting
    // =========================================================================

    public function test_sortRows_numericNativeSorting(): void
    {
        $_GET['sort'] = 'id';
        unset($_GET['sort_dir']);

        $grid = $this->createGrid();
        $grid->columns()->getByName('id')->useNativeSorting();

        // Add in non-sorted numeric order including values where lexicographic ≠ numeric
        $grid->rows()->addArray(['id' => '10', 'name' => 'Ten']);
        $grid->rows()->addArray(['id' => '9', 'name' => 'Nine']);
        $grid->rows()->addArray(['id' => '100', 'name' => 'OneHundred']);

        $rows = $grid->rows()->getRows();
        $grid->sorting()->sortRows($rows);

        // Numeric sort: 9 < 10 < 100 (lexicographic would give 10, 100, 9)
        $this->assertInstanceOf(StandardRow::class, $rows[0]);
        $this->assertInstanceOf(StandardRow::class, $rows[1]);
        $this->assertInstanceOf(StandardRow::class, $rows[2]);
        $this->assertSame('9', $rows[0]->getCell('id')->getValue());
        $this->assertSame('10', $rows[1]->getCell('id')->getValue());
        $this->assertSame('100', $rows[2]->getCell('id')->getValue());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createGrid(): DataGrid
    {
        $grid = DataGrid::create();
        $grid->columns()->add('id', 'ID');
        $grid->columns()->add('name', 'Name');
        return $grid;
    }
}
