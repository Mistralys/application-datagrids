<?php

declare(strict_types=1);

namespace AppUtils\Tests\Actions;

use AppUtils\Grids\DataGrid;
use AppUtils\Tests\TestClasses\InMemoryStorage;
use PHPUnit\Framework\TestCase;

class GridActionsTest extends TestCase
{
    // =========================================================================
    // processSubmittedActions() — 7 scenarios
    // =========================================================================

    /**
     * Scenario 1: No POST data — null with empty $_POST → false.
     */
    public function test_processSubmittedActions_noPostData(): void
    {
        $grid = $this->createGridWithAction();

        // null falls back to $_POST, which contains no grid_action key
        $this->assertFalse($grid->actions()->processSubmittedActions(null));
    }

    /**
     * Scenario 2: Explicit empty array → false (not treated as $_POST fallback).
     */
    public function test_processSubmittedActions_explicitEmptyArray(): void
    {
        $grid = $this->createGridWithAction();

        $this->assertFalse($grid->actions()->processSubmittedActions([]));
    }

    /**
     * Scenario 3: Post data present but grid_action key missing → false.
     */
    public function test_processSubmittedActions_missingActionField(): void
    {
        $grid = $this->createGridWithAction();

        $this->assertFalse($grid->actions()->processSubmittedActions([
            'some_other_field' => 'value',
        ]));
    }

    /**
     * Scenario 4: grid_action set to an unregistered name → false.
     */
    public function test_processSubmittedActions_unknownAction(): void
    {
        $grid = $this->createGridWithAction();

        $this->assertFalse($grid->actions()->processSubmittedActions([
            'grid_action' => 'nonexistent_action',
        ]));
    }

    /**
     * Scenario 5: SeparatorAction is silently skipped during matching.
     */
    public function test_processSubmittedActions_separatorSkipping(): void
    {
        $grid = $this->createGrid();

        $grid->actions()->add('first', 'First');
        $grid->actions()->separator();
        $grid->actions()->add('second', 'Second');

        // The separator does not interfere with matching the second action.
        $this->assertTrue($grid->actions()->processSubmittedActions([
            'grid_action' => 'second',
        ]));
    }

    /**
     * Scenario 6: Successful callback invocation.
     */
    public function test_processSubmittedActions_successfulCallback(): void
    {
        $grid = $this->createGrid();

        $callbackArgs = null;
        $callbackCount = 0;

        $grid->actions()->setValueColumn('id');
        $grid->actions()->add('delete', 'Delete')
            ->setCallback(function (array $selected) use (&$callbackArgs, &$callbackCount): void {
                $callbackArgs = $selected;
                $callbackCount++;
            });

        $result = $grid->actions()->processSubmittedActions([
            'grid_action' => 'delete',
            'selected' => ['1', '2'],
        ]);

        $this->assertTrue($result);
        $this->assertSame(1, $callbackCount, 'Callback must be invoked exactly once.');
        $this->assertSame(['1', '2'], $callbackArgs, 'Callback must receive the selected values.');
    }

    /**
     * Scenario 7: Action matched but no callback set → returns true, no error.
     */
    public function test_processSubmittedActions_noCallbackSet(): void
    {
        $grid = $this->createGrid();

        $grid->actions()->add('archive', 'Archive');

        $this->assertTrue($grid->actions()->processSubmittedActions([
            'grid_action' => 'archive',
        ]));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Creates a grid with columns and a single registered action (with value column).
     */
    private function createGridWithAction(): DataGrid
    {
        $grid = $this->createGrid();
        $grid->actions()->setValueColumn('id');
        $grid->actions()->add('delete', 'Delete');

        return $grid;
    }

    /**
     * Creates a grid with two columns: id and name.
     */
    private function createGrid(): DataGrid
    {
        $grid = DataGrid::create('actions-test', new InMemoryStorage());
        $grid->columns()->add('id', 'ID');
        $grid->columns()->add('name', 'Name');

        return $grid;
    }
}
