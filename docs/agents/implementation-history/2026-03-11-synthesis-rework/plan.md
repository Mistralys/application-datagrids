# Plan — Synthesis Observations Rework

## Summary

This plan addresses all 7 actionable items from the "Observations & Recommendations" section of the [2026-03-09 Rework-1 Synthesis](../2026-03-09-grid-actions-pagination-rework-1/synthesis.md). Changes span JS hardening, style harmonization, null-check preference, return type refinement, warning deduplication, example cleanup, and constant visibility.

## Architectural Context

| Module | Key Files | Relevance |
|---|---|---|
| Renderer | `BaseGridRenderer.php`, `Bootstrap5Renderer.php` | Item 1: `$inputId` JS hardening |
| Pagination | `GridPagination.php` | Item 2: constructor promotion harmonization, Item 7: `PAGE_SENTINEL` visibility |
| Core | `DataGrid.php` | Item 3: null-check style (`isset`) |
| Rows | `StandardRow.php` | Item 4: `getGrid()` return type, Item 5: warning deduplication |
| Rows | `RowManager.php` | Item 4: `getGrid()` return type (dependency) |
| Examples | `examples/3-grid-actions.php` | Item 6: use `processActions()` facade |

## Items

### Item 1 — Harden `$inputId` JS interpolation (Low)

In `BaseGridRenderer::createPageJumpInput()` and `Bootstrap5Renderer::createBootstrapPageJumpInput()`, the `$inputId` is interpolated directly into JavaScript via `document.getElementById('{$inputId}')`. While IDs are internally generated alphanumeric strings (low risk), this should be hardened with `json_encode()` for consistency with the URL template fix already applied.

**Change:** Replace `'{$inputId}'` with the output of `json_encode($inputId)` (which includes its own quotes) in both methods.

**Files:**
- `src/Grids/Renderer/BaseGridRenderer.php` — `createPageJumpInput()`
- `src/Grids/Renderer/Types/Bootstrap5Renderer.php` — `createBootstrapPageJumpInput()`

### Item 2 — Harmonize constructor promotion (Low)

`GridPagination` uses constructor property promotion (`private DataGridInterface $grid`), while `GridActions` and `SelectionCell` use explicit assignment. Harmonize by converting `GridPagination` to use explicit assignment, matching the codebase convention.

**Change:** Replace constructor promotion with explicit property declaration and assignment in `GridPagination`.

**Files:**
- `src/Grids/Pagination/GridPagination.php`

### Item 3 — Unify null-check style to `isset()` (Low)

The `actions()` accessor uses `isset($this->actions)` while `generateOutput()` and other methods use `$this->actions !== null`. Per user preference, the codebase should standardize on `isset()` for nullable property checks.

**Change:** In `DataGrid.php`, replace all `$this->actions !== null` and `$this->pagination !== null` occurrences with `isset($this->actions)` / `isset($this->pagination)`.

**Files:**
- `src/Grids/DataGrid.php`

### Item 4 — `StandardRow::getGrid()` return `DataGridInterface` (Low)

`StandardRow::getGrid()` returns the concrete `DataGrid` rather than `DataGridInterface`. This should follow the interface-driven pattern. The `GridRowInterface` already declares `getGrid(): DataGridInterface`.

**Dependency:** `RowManager::getGrid()` also returns `DataGrid`. This must be changed to `DataGridInterface` first, since `StandardRow` delegates to it.

**Change:**
1. Change `RowManager::getGrid()` return type from `DataGrid` to `DataGridInterface`. Update the property type to `DataGridInterface`.
2. Change `StandardRow::getGrid()` return type from `DataGrid` to `DataGridInterface`.
3. Remove the `use AppUtils\Grids\DataGrid;` import from `StandardRow.php` if no longer needed.

**Files:**
- `src/Grids/Rows/RowManager.php`
- `src/Grids/Rows/Types/StandardRow.php`

### Item 5 — Throw exception when no value column is set (Medium)

The `E_USER_WARNING` from Bug #6 fires on every `getSelectionCell()` call (once per row). Instead of deduplicating the warning, replace it with a `DataGridException` thrown from `StandardRow::getSelectionCell()`. If actions are enabled but no value column has been set, this is a developer configuration error and should fail hard.

**Change:** In `StandardRow::getSelectionCell()`, replace the `trigger_error()` call with a `throw new DataGridException(...)`. The existing tests that catch warnings must be updated to expect the exception instead.

**Files:**
- `src/Grids/Rows/Types/StandardRow.php` — replace `trigger_error()` with `throw new DataGridException()`
- `tests/Rows/StandardRowTest.php` — update `test_getSelectionCell_warnsOnEmptyValue` to expect `DataGridException`
- `tests/Cells/SelectionCellTest.php` — update `test_renderContent_emptyValue` to expect `DataGridException` (the empty-value render path is no longer reachable)

### Item 6 — Example uses `processActions()` facade (Medium)

`examples/3-grid-actions.php` calls `$grid->actions()->processSubmittedActions()` directly. It should use the public `$grid->processActions()` facade to showcase the preferred API.

**Change:** Replace `$grid->actions()->processSubmittedActions();` with `$grid->processActions();`.

**Files:**
- `examples/3-grid-actions.php`

### Item 7 — Make `PAGE_SENTINEL` public (Medium)

The `PAGE_SENTINEL` constant in `GridPagination` is `private`. External code (e.g., custom pagination providers) may need to reference it. Make it `public`.

**Change:** Change `private const PAGE_SENTINEL` to `public const PAGE_SENTINEL`.

**Files:**
- `src/Grids/Pagination/GridPagination.php`

## Detailed Steps

### WP-001 — JS Hardening & Style Cleanup (Items 1, 2)

**Step 1 — Harden `$inputId` in `BaseGridRenderer`:**
In `createPageJumpInput()`, change:
```php
$js = "var p = document.getElementById('{$inputId}').value; ...";
```
to:
```php
$encodedInputId = json_encode($inputId);
$js = "var p = document.getElementById({$encodedInputId}).value; ...";
```

**Step 2 — Harden `$inputId` in `Bootstrap5Renderer`:**
Same change in `createBootstrapPageJumpInput()`.

**Step 3 — Remove constructor promotion in `GridPagination`:**
Replace `public function __construct(private DataGridInterface $grid) {}` with:
```php
private DataGridInterface $grid;

public function __construct(DataGridInterface $grid)
{
    $this->grid = $grid;
}
```

### WP-002 — Null-Check & Return Type Unification (Items 3, 4)

**Step 1 — Unify null-checks in `DataGrid.php`:**
Replace all `$this->actions !== null` with `isset($this->actions)` and `$this->pagination !== null` with `isset($this->pagination)`.

Affected locations in `generateOutput()`:
- Line 71: `if (!$this->actionsProcessed && $this->actions !== null)` → `if (!$this->actionsProcessed && isset($this->actions))`
- Line 96: `if ($this->pagination !== null && ...)` → `if (isset($this->pagination) && ...)`
- Line 98: `if($this->actions !== null)` → `if(isset($this->actions))`

In `hasActions()`:
- `return $this->actions !== null && ...` → `return isset($this->actions) && ...`

In `processActions()`:
- `if ($this->actions === null)` → `if (!isset($this->actions))`

**Step 2 — Change `RowManager::getGrid()` return type:**
Change property type and return type from `DataGrid` to `DataGridInterface`.

**Step 3 — Change `StandardRow::getGrid()` return type:**
Change return type from `DataGrid` to `DataGridInterface`. Remove unused `DataGrid` import.

### WP-003 — Exception on Missing Value Column & Constant Visibility (Items 5, 7)

**Step 1 — Replace warning with exception in `StandardRow::getSelectionCell()`:**
Replace the `trigger_error()` block with:
```php
if ($this->getSelectValue() === '') {
    throw new DataGridException(
        'DataGrid: Row selection is active but no value column is configured. '
        . 'Call $grid->actions()->setValueColumn() before rendering.',
        null,
        DataGridException::ERROR_NO_VALUE_COLUMN
    );
}
```
Add the `use AppUtils\Grids\DataGridException;` import.

**Step 2 — Add error code constant to `DataGridException`:**
Add `public const ERROR_NO_VALUE_COLUMN = <next available code>;` (check existing constants for numbering).

**Step 3 — Update `StandardRowTest`:**
Rewrite `test_getSelectionCell_warnsOnEmptyValue` to use `$this->expectException(DataGridException::class)` instead of the `set_error_handler` / `trigger_error` pattern. Remove the warning-related assertions.

**Step 4 — Update `SelectionCellTest`:**
Rewrite `test_renderContent_emptyValue`: since `getSelectionCell()` now throws before returning a cell, the empty-value render path is unreachable. Change the test to verify that the exception is thrown (i.e., `$this->expectException(DataGridException::class)`) and remove the HTML assertions for the empty-value case.

**Step 5 — Make `PAGE_SENTINEL` public:**
Change `private const PAGE_SENTINEL` to `public const PAGE_SENTINEL`.

### WP-004 — Example Update (Item 6)

**Step 1 — Use `processActions()` facade:**
Replace `$grid->actions()->processSubmittedActions();` with `$grid->processActions();`.

### WP-005 — Verification & Manifest Updates

**Step 1 — Run `composer test`** to confirm all 47 tests still pass.

**Step 2 — Run `composer analyze`** to confirm zero new PHPStan errors.

**Step 3 — Update manifest documents:**

| Document | Changes |
|---|---|
| `api-surface.md` | `GridPagination::PAGE_SENTINEL` visibility → public. `RowManager::getGrid()` return type → `DataGridInterface`. `StandardRow::getGrid()` return type → `DataGridInterface`. `DataGridException` gains `ERROR_NO_VALUE_COLUMN` constant. |
| `constraints.md` | Document that missing value column now throws `DataGridException` (was `E_USER_WARNING`). Remove recommendation items that are now resolved. |
| `data-flows.md` | No changes expected. |
| `file-tree.md` | No changes expected (no new files). |

## Dependencies

- Item 4 Step 2 (`RowManager`) must precede Step 3 (`StandardRow`), since `StandardRow::getGrid()` delegates to `RowManager::getGrid()`.
- Item 5 Step 2 (`DataGridException` constant) must precede Step 1 (`StandardRow` throw).
- All other items are independent and can be parallelized.

## Acceptance Criteria

1. `$inputId` values in page-jump JS are encoded via `json_encode()` in both renderers.
2. `GridPagination` uses explicit constructor assignment (no promotion).
3. All nullable property null-checks in `DataGrid.php` use `isset()`.
4. `StandardRow::getGrid()` and `RowManager::getGrid()` return `DataGridInterface`.
5. Rendering a grid with actions enabled but no value column throws `DataGridException`.
6. `examples/3-grid-actions.php` uses `$grid->processActions()`.
7. `PAGE_SENTINEL` is `public const`.
8. All 47 existing tests pass.
9. PHPStan level 6 reports zero new errors.
10. Manifest documents are updated.

## Risks & Mitigations

| Risk | Mitigation |
|---|---|
| `RowManager::getGrid()` return type change breaks callers expecting `DataGrid` | `DataGrid` implements `DataGridInterface`; all public API methods are on the interface. Low risk. Run PHPStan to catch any type mismatches. |
| `StandardRowTest` and `SelectionCellTest` warning tests break | Both tests currently assert `E_USER_WARNING`. Update to `expectException(DataGridException::class)`. Test count may decrease by 1 if the empty-value render test is merged into the exception test. |
| `isset()` vs `!== null` semantic difference for uninitialized properties | Not applicable — all affected properties are initialized (typed nullable with `= null`). `isset()` and `!== null` are equivalent here. |

## Out of Scope

- Fixing the `WebcomicsBuilder` namespace anomaly.
- Implementing remaining stubs.
- Any new test files (existing tests are updated in place, not created).
- CI/CD or tooling changes.
