<?php

declare(strict_types=1);

namespace AppUtils\Tests\Sorting;

use AppUtils\Grids\Columns\SortMode;
use AppUtils\Grids\DataGrid;
use AppUtils\Grids\DataGridInterface;
use AppUtils\Grids\Rows\Types\MergedRow;
use AppUtils\Grids\Sorting\SortManager;
use PHPUnit\Framework\TestCase;
use AppUtils\Grids\Rows\Types\StandardRow;

class ColumnSortingTest extends TestCase
{
    /**
     * @var array<string,mixed>
     */
    private array $originalGet = [];
    private string|null $originalRequestUri = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalGet = $_GET;
        $this->originalRequestUri = $_SERVER['REQUEST_URI'] ?? null;
    }

    protected function tearDown(): void
    {
        $_GET = $this->originalGet;
        if ($this->originalRequestUri !== null) {
            $_SERVER['REQUEST_URI'] = $this->originalRequestUri;
        } else {
            unset($_SERVER['REQUEST_URI']);
        }
        parent::tearDown();
    }

    // =========================================================================
    // Column sortable flags (tests 1-4)
    // =========================================================================

    public function test_useNativeSorting_marksSortable(): void
    {
        $col = $this->createGrid()->columns()->getByName('name');
        $col->useNativeSorting();

        $this->assertTrue($col->isSortable());
        $this->assertSame(SortMode::Native, $col->getSortMode());
    }

    public function test_useCallbackSorting_marksSortable(): void
    {
        $col = $this->createGrid()->columns()->getByName('name');
        $col->useCallbackSorting(static fn($a, $b, $c): int => 0);

        $this->assertTrue($col->isSortable());
        $this->assertSame(SortMode::Callback, $col->getSortMode());
        $this->assertNotNull($col->getSortCallback());
    }

    public function test_useManualSorting_marksSortable(): void
    {
        $col = $this->createGrid()->columns()->getByName('name');
        $col->useManualSorting();

        $this->assertTrue($col->isSortable());
        $this->assertSame(SortMode::Manual, $col->getSortMode());
    }

    public function test_column_notSortableByDefault(): void
    {
        $col = $this->createGrid()->columns()->getByName('name');

        $this->assertFalse($col->isSortable());
        $this->assertNull($col->getSortMode());
    }

    // =========================================================================
    // SortManager::getSortColumn() (tests 5-7)
    // =========================================================================

    public function test_getSortColumn_returnsNull_whenNoRequest(): void
    {
        $_GET = [];
        $grid = $this->createGrid();
        $grid->columns()->getByName('name')->useNativeSorting();

        $this->assertNull($grid->sorting()->getSortColumn());
    }

    public function test_getSortColumn_resolvesFromGet(): void
    {
        $_GET['sort'] = 'name';
        $grid = $this->createGrid();
        $grid->columns()->getByName('name')->useNativeSorting();

        $col = $grid->sorting()->getSortColumn();

        $this->assertNotNull($col);
        $this->assertSame('name', $col->getName());
    }

    public function test_getSortColumn_ignoresNonSortableColumn(): void
    {
        $_GET['sort'] = 'name';
        $grid = $this->createGrid();
        // 'name' column is intentionally not marked as sortable

        $this->assertNull($grid->sorting()->getSortColumn());
    }

    // =========================================================================
    // SortManager::getSortDir() (tests 8-10)
    // =========================================================================

    public function test_getSortDir_defaultsToAsc(): void
    {
        $_GET['sort'] = 'name';
        unset($_GET['sort_dir']);
        $grid = $this->createGrid();
        $grid->columns()->getByName('name')->useNativeSorting();

        $this->assertSame(DataGridInterface::SORT_ASC, $grid->sorting()->getSortDir());
    }

    public function test_getSortDir_parsesDESC(): void
    {
        $_GET['sort'] = 'name';
        $_GET['sort_dir'] = 'DESC';
        $grid = $this->createGrid();
        $grid->columns()->getByName('name')->useNativeSorting();

        $this->assertSame(DataGridInterface::SORT_DESC, $grid->sorting()->getSortDir());
    }

    public function test_getSortDir_ignoresInvalidValues(): void
    {
        $_GET['sort'] = 'name';
        $_GET['sort_dir'] = 'INVALID';
        $grid = $this->createGrid();
        $grid->columns()->getByName('name')->useNativeSorting();

        $this->assertSame(DataGridInterface::SORT_ASC, $grid->sorting()->getSortDir());
    }

    // =========================================================================
    // Row sorting (tests 11-15)
    // =========================================================================

    public function test_nativeSorting_sortsStringAsc(): void
    {
        $_GET['sort'] = 'name';
        $grid = $this->createGrid();
        $grid->columns()->getByName('name')->useNativeSorting();

        $grid->rows()->addArray(['id' => '3', 'name' => 'Charlie']);
        $grid->rows()->addArray(['id' => '1', 'name' => 'Alice']);
        $grid->rows()->addArray(['id' => '2', 'name' => 'Bob']);

        $rows = $grid->rows()->getRows();
        $sorting = $grid->sorting();
        if ($sorting instanceof SortManager) {
            $sorting->sortRows($rows);
        }

        $row0 = $rows[0];
        $row1 = $rows[1];
        $row2 = $rows[2];
        $this->assertInstanceOf(StandardRow::class, $row0);
        $this->assertInstanceOf(StandardRow::class, $row1);
        $this->assertInstanceOf(StandardRow::class, $row2);
        $this->assertSame('Alice', $row0->getCell('name')->getValue());
        $this->assertSame('Bob', $row1->getCell('name')->getValue());
        $this->assertSame('Charlie', $row2->getCell('name')->getValue());
    }

    public function test_nativeSorting_sortsStringDesc(): void
    {
        $_GET['sort'] = 'name';
        $_GET['sort_dir'] = 'DESC';
        $grid = $this->createGrid();
        $grid->columns()->getByName('name')->useNativeSorting();

        $grid->rows()->addArray(['id' => '1', 'name' => 'Alice']);
        $grid->rows()->addArray(['id' => '2', 'name' => 'Bob']);
        $grid->rows()->addArray(['id' => '3', 'name' => 'Charlie']);

        $rows = $grid->rows()->getRows();
        $sorting = $grid->sorting();
        if ($sorting instanceof SortManager) {
            $sorting->sortRows($rows);
        }

        $row0 = $rows[0];
        $row1 = $rows[1];
        $row2 = $rows[2];
        $this->assertInstanceOf(StandardRow::class, $row0);
        $this->assertInstanceOf(StandardRow::class, $row1);
        $this->assertInstanceOf(StandardRow::class, $row2);
        $this->assertSame('Charlie', $row0->getCell('name')->getValue());
        $this->assertSame('Bob', $row1->getCell('name')->getValue());
        $this->assertSame('Alice', $row2->getCell('name')->getValue());
    }

    public function test_nativeSorting_sortsNumericAsc(): void
    {
        $_GET['sort'] = 'id';
        $grid = $this->createGrid();
        $grid->columns()->getByName('id')->useNativeSorting();

        $grid->rows()->addArray(['id' => '30', 'name' => 'Charlie']);
        $grid->rows()->addArray(['id' => '10', 'name' => 'Alice']);
        $grid->rows()->addArray(['id' => '20', 'name' => 'Bob']);

        $rows = $grid->rows()->getRows();
        $sorting = $grid->sorting();
        if ($sorting instanceof SortManager) {
            $sorting->sortRows($rows);
        }

        $row0 = $rows[0];
        $row1 = $rows[1];
        $row2 = $rows[2];
        $this->assertInstanceOf(StandardRow::class, $row0);
        $this->assertInstanceOf(StandardRow::class, $row1);
        $this->assertInstanceOf(StandardRow::class, $row2);
        $this->assertSame('Alice', $row0->getCell('name')->getValue());
        $this->assertSame('Bob', $row1->getCell('name')->getValue());
        $this->assertSame('Charlie', $row2->getCell('name')->getValue());
    }

    public function test_callbackSorting_usesCallback(): void
    {
        $_GET['sort'] = 'name';
        $grid = $this->createGrid();

        // Callback: reverse-alphabetical (Z before A)
        $grid->columns()->getByName('name')->useCallbackSorting(
            static function (StandardRow $a, StandardRow $b, mixed $col): int {
                return strcasecmp(
                    (string)$b->getCell('name')->getValue(),
                    (string)$a->getCell('name')->getValue()
                );
            }
        );

        $grid->rows()->addArray(['id' => '1', 'name' => 'Alice']);
        $grid->rows()->addArray(['id' => '2', 'name' => 'Bob']);
        $grid->rows()->addArray(['id' => '3', 'name' => 'Charlie']);

        $rows = $grid->rows()->getRows();
        $sorting = $grid->sorting();
        if ($sorting instanceof SortManager) {
            $sorting->sortRows($rows);
        }

        $row0 = $rows[0];
        $row1 = $rows[1];
        $row2 = $rows[2];
        $this->assertInstanceOf(StandardRow::class, $row0);
        $this->assertInstanceOf(StandardRow::class, $row1);
        $this->assertInstanceOf(StandardRow::class, $row2);
        $this->assertSame('Charlie', $row0->getCell('name')->getValue());
        $this->assertSame('Bob', $row1->getCell('name')->getValue());
        $this->assertSame('Alice', $row2->getCell('name')->getValue());
    }

    public function test_manualSorting_doesNotReorder(): void
    {
        $_GET['sort'] = 'name';
        $grid = $this->createGrid();
        $grid->columns()->getByName('name')->useManualSorting();

        $grid->rows()->addArray(['id' => '3', 'name' => 'Charlie']);
        $grid->rows()->addArray(['id' => '1', 'name' => 'Alice']);
        $grid->rows()->addArray(['id' => '2', 'name' => 'Bob']);

        $rows = $grid->rows()->getRows();
        $sorting = $grid->sorting();
        if ($sorting instanceof SortManager) {
            $sorting->sortRows($rows);
        }

        $row0 = $rows[0];
        $row1 = $rows[1];
        $row2 = $rows[2];
        $this->assertInstanceOf(StandardRow::class, $row0);
        $this->assertInstanceOf(StandardRow::class, $row1);
        $this->assertInstanceOf(StandardRow::class, $row2);
        $this->assertSame('Charlie', $row0->getCell('name')->getValue());
        $this->assertSame('Alice', $row1->getCell('name')->getValue());
        $this->assertSame('Bob', $row2->getCell('name')->getValue());
    }

    // =========================================================================
    // getSortURL() (tests 16-17)
    // =========================================================================

    public function test_getSortURL_togglesDirection(): void
    {
        $_GET['sort'] = 'name';
        $_GET['sort_dir'] = 'ASC';
        $_SERVER['REQUEST_URI'] = '/grid?sort=name&sort_dir=ASC';

        $grid = $this->createGrid();
        $col = $grid->columns()->getByName('name');
        $col->useNativeSorting();

        $url = $grid->sorting()->getSortURL($col);

        $this->assertStringContainsString('sort_dir=DESC', $url);
        $this->assertStringContainsString('sort=name', $url);
    }

    public function test_getSortURL_defaultsAsc_forOtherColumn(): void
    {
        $_GET['sort'] = 'name';
        $_GET['sort_dir'] = 'ASC';
        $_SERVER['REQUEST_URI'] = '/grid?sort=name&sort_dir=ASC';

        $grid = $this->createGrid();
        $grid->columns()->getByName('name')->useNativeSorting();
        $idCol = $grid->columns()->getByName('id');
        $idCol->useNativeSorting();

        $url = $grid->sorting()->getSortURL($idCol);

        $this->assertStringContainsString('sort=id', $url);
        $this->assertStringContainsString('sort_dir=ASC', $url);
    }

    // =========================================================================
    // Merged row preservation (test 18)
    // =========================================================================

    public function test_sortingPreservesMergedRows(): void
    {
        $_GET['sort'] = 'name';
        $grid = $this->createGrid();
        $grid->columns()->getByName('name')->useNativeSorting();

        // Insert: Charlie [0], MergedRow [1], Alice [2], Bob [3]
        $grid->rows()->addArray(['id' => '3', 'name' => 'Charlie']);
        $grid->rows()->addMerged('Section Header');
        $grid->rows()->addArray(['id' => '1', 'name' => 'Alice']);
        $grid->rows()->addArray(['id' => '2', 'name' => 'Bob']);

        $rows = $grid->rows()->getRows();
        $sorting = $grid->sorting();
        if ($sorting instanceof SortManager) {
            $sorting->sortRows($rows);
        }

        // The MergedRow must remain at its original index 1
        $this->assertInstanceOf(MergedRow::class, $rows[1]);
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
