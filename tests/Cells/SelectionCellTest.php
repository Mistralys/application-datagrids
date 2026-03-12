<?php

declare(strict_types=1);

namespace AppUtils\Tests\Cells;

use AppUtils\Grids\DataGrid;
use AppUtils\Grids\DataGridException;
use PHPUnit\Framework\TestCase;

class SelectionCellTest extends TestCase
{
    // =========================================================================
    // renderContent()
    // =========================================================================

    /**
     * Verify the checkbox contains the correct type, name, and value attributes.
     */
    public function test_renderContent_outputMarkup(): void
    {
        $grid = $this->createGrid();

        $grid->actions()->setValueColumn('id');
        $grid->actions()->add('delete', 'Delete');

        $row = $grid->rows()->addArray(['id' => '42', 'name' => 'Test']);

        $cell = $row->getSelectionCell();
        $this->assertNotNull($cell);

        $html = $cell->renderContent();

        $this->assertStringContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('name="selected[]"', $html);
        $this->assertStringContainsString('value="42"', $html);
    }

    /**
     * When no value column is set, getSelectionCell() throws DataGridException.
     * The empty-value render path is no longer reachable.
     */
    public function test_renderContent_emptyValue(): void
    {
        $this->expectException(DataGridException::class);

        $grid = $this->createGrid();

        // Register an action but do NOT set a value column.
        $grid->actions()->add('delete', 'Delete');

        $row = $grid->rows()->addArray(['id' => '1', 'name' => 'Test']);

        $row->getSelectionCell();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createGrid(): DataGrid
    {
        $grid = DataGrid::create('cell-test');
        $grid->columns()->add('id', 'ID');
        $grid->columns()->add('name', 'Name');

        return $grid;
    }
}
