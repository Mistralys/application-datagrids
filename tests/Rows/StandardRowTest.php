<?php

declare(strict_types=1);

namespace AppUtils\Tests\Rows;

use AppUtils\Grids\DataGrid;
use AppUtils\Grids\DataGridException;
use PHPUnit\Framework\TestCase;

class StandardRowTest extends TestCase
{
    // =========================================================================
    // getSelectValue()
    // =========================================================================

    /**
     * With a configured value column, getSelectValue() returns the cell value.
     */
    public function test_getSelectValue_withValueColumn(): void
    {
        $grid = $this->createGrid();

        $grid->actions()->setValueColumn('id');
        $grid->actions()->add('delete', 'Delete');

        $row = $grid->rows()->addArray(['id' => '42', 'name' => 'Test']);

        $this->assertSame('42', $row->getSelectValue());
    }

    /**
     * Without a value column, getSelectValue() returns an empty string.
     */
    public function test_getSelectValue_withoutValueColumn(): void
    {
        $grid = $this->createGrid();

        // Configure actions but do NOT set a value column.
        $grid->actions()->add('delete', 'Delete');

        $row = $grid->rows()->addArray(['id' => '42', 'name' => 'Test']);

        $this->assertSame('', $row->getSelectValue());
    }

    // =========================================================================
    // isSelectable()
    // =========================================================================

    /**
     * isSelectable() returns true when at least one action is registered.
     */
    public function test_isSelectable_withActions(): void
    {
        $grid = $this->createGrid();

        $grid->actions()->add('delete', 'Delete');

        $row = $grid->rows()->addArray(['id' => '1', 'name' => 'Test']);

        $this->assertTrue($row->isSelectable());
    }

    /**
     * isSelectable() returns false when no actions are configured.
     * Note: calling actions() lazily instantiates GridActions, but with no
     * registered actions hasActions() returns false.
     */
    public function test_isSelectable_withoutActions(): void
    {
        $grid = $this->createGrid();

        $row = $grid->rows()->addArray(['id' => '1', 'name' => 'Test']);

        $this->assertFalse($row->isSelectable());
    }

    // =========================================================================
    // getSelectionCell()
    // =========================================================================

    // When actions are configured but no value column is set, the method throws
    // DataGridException::ERROR_NO_VALUE_COLUMN (not E_USER_WARNING as in Bug #6).

    /**
     * When actions exist but no value column is set, getSelectionCell()
     * throws DataGridException.
     */
    public function test_getSelectionCell_warnsOnEmptyValue(): void
    {
        $this->expectException(DataGridException::class);

        $grid = $this->createGrid();

        // Register an action without setting a value column.
        $grid->actions()->add('delete', 'Delete');

        $row = $grid->rows()->addArray(['id' => '1', 'name' => 'Test']);

        $row->getSelectionCell();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createGrid(): DataGrid
    {
        $grid = DataGrid::create('row-test');
        $grid->columns()->add('id', 'ID');
        $grid->columns()->add('name', 'Name');

        return $grid;
    }
}
