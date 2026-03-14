# Plan

## Summary

Resolve all outstanding technical debt and implement the strategic recommendations surfaced in the [2026-03-11 Synthesis Report](../2026-03-11-synthesis-rework/synthesis.md). This plan covers 5 work areas: (1) eliminating the 14 pre-existing PHPStan level-6 errors by implementing missing interface methods in `HeaderRow`/`MergedRow` and adding type annotations to `RowManager`; (2) hardening the two `json_encode()` page-jump calls with `JSON_THROW_ON_ERROR`; (3) extracting the duplicated page-jump builder into a shared base-class template method; (4) removing dead code in `SelectionCell` and renaming its stale test; (5) completing the dependency-inversion pattern in `RendererManager`. After all changes, the project should reach **zero PHPStan level-6 errors** and be ready for an incremental level upgrade evaluation.

## Architectural Context

- **Row hierarchy:** `GridRowInterface` → `BaseGridRow` (abstract) → `StandardRow`, `HeaderRow`, `MergedRow`. `GridRowInterface` requires `getGrid(): DataGridInterface` and `isSelectable(): bool`. Currently only `StandardRow` implements these; `HeaderRow` and `MergedRow` inherit from `BaseGridRow` which declares the interface but provides no implementations, causing 4 PHPStan errors.
- **Row ownership:** `StandardRow` receives `RowManager` in its constructor and derives the grid from it. `HeaderRow` is created by `RowManager::getHeaderRow()`. `MergedRow` is created by `RowManager::addMerged()` — currently without any grid reference.
- **Renderer hierarchy:** `GridRendererInterface` → `BaseGridRenderer` (abstract, accepts `DataGridInterface`) → `DefaultRenderer`, `Bootstrap5Renderer`. The page-jump input builder is duplicated: `BaseGridRenderer::createPageJumpInput()` (protected) and `Bootstrap5Renderer::createBootstrapPageJumpInput()` (private). They differ only in CSS classes and container element wrapping.
- **Renderer management:** `RendererManager` holds a concrete `DataGrid` property/constructor parameter, while `BaseGridRenderer` already uses `DataGridInterface`. This is the last remaining concrete coupling in internal components.
- **SelectionCell:** After the synthesis rework, the empty-value guard was moved upstream to `StandardRow::getSelectionCell()`. The `SelectionCell::renderContent()` method no longer has an empty-value branch (already removed), but the test name `test_renderContent_emptyValue` is stale.
- **PHPStan configuration:** Level 6, 14 pre-existing errors distributed across `BaseGridColumn.php` (3), `DataGrid.php` (3), `GridForm.php` (1), `RowManager.php` (3), `HeaderRow.php` (2), `MergedRow.php` (2).

## Approach / Architecture

### Work Area 1: Fix `HeaderRow` & `MergedRow` Interface Compliance (4 errors)

Both classes need `getGrid()` and `isSelectable()`. The canonical approach is to push these implementations into `BaseGridRow` so all row types inherit them:

- Add a `?RowManager` property to `BaseGridRow` with a setter `setRowManager(RowManager $manager): self`.
- Implement `getGrid()` in `BaseGridRow`: delegate to `$this->manager->getGrid()`, throw `DataGridException` if no manager set.
- Implement `isSelectable()` in `BaseGridRow`: delegate to `$this->manager->getGrid()->hasActions()`, return `false` if no manager is set. This is safe because `HeaderRow` and `MergedRow` are not selectable.
- Call `setRowManager()` from `RowManager::registerRow()` to inject the manager into every row on registration (including `HeaderRow`, `MergedRow`, and `StandardRow`).
- Refactor `StandardRow` to remove its own `getGrid()` and `isSelectable()` implementations, relying on the inherited ones from `BaseGridRow`.

**Alternative considered (and rejected):** Implement `getGrid()` and `isSelectable()` directly in `HeaderRow` and `MergedRow`. This was rejected because: (a) `MergedRow` has no constructor access to the grid, so it would need a setter anyway; (b) it would duplicate the pattern already in `StandardRow`; (c) pushing to the base class consolidates the logic and prevents future row types from encountering the same problem.

**New exception constant:** Add `DataGridException::ERROR_NO_ROW_MANAGER` for the guard in `BaseGridRow::getGrid()`.

### Work Area 2: `RowManager` Type Annotations (3 errors)

- `addArrays(array $rows)` → `@param array<int, array<string, mixed>> $rows`
- `addArray(array $columnValues)` → `@param array<string, mixed> $columnValues`  
- `addMerged($content)` → add native type `string|StringableInterface|NULL`

### Work Area 3: `json_encode()` Robustness (0 PHPStan errors at level 6, prevents future level-8 issues)

Add `JSON_THROW_ON_ERROR` flag to both `json_encode()` calls in:
- `BaseGridRenderer::createPageJumpInput()` (2 calls)
- `Bootstrap5Renderer::createBootstrapPageJumpInput()` (2 calls)

### Work Area 4: Extract Shared Page-Jump Template Method

Refactor to eliminate the near-identical duplication:
- Rename `BaseGridRenderer::createPageJumpInput()` to be the canonical builder.
- Add a `protected function createPageJumpContainer(HTMLTag $input, HTMLTag $button): HTMLTag` hook method in `BaseGridRenderer` that wraps `$input` and `$button` in a plain `<span>`.
- Override `createPageJumpContainer()` in `Bootstrap5Renderer` to wrap in the Bootstrap-styled `<div class="d-flex align-items-center gap-2 mt-2">` and add Bootstrap classes to the input/button.
- Remove `Bootstrap5Renderer::createBootstrapPageJumpInput()` entirely.
- Update `Bootstrap5Renderer::renderPaginationRow()` to call the inherited `createPageJumpInput()` instead.

### Work Area 5: Dead Code Removal & Test Rename

- **Test rename:** `SelectionCellTest::test_renderContent_emptyValue` → `test_getSelectionCell_throwsOnMissingValueColumn`.
- **Remove the `SelectionCell::renderContent()` dead branch:** Verify that the current code has no dead branch (exploration confirms it's already gone). If residual dead code exists, remove it.

### Work Area 6: `RendererManager` Dependency Inversion

- Change `RendererManager::$grid` from `DataGrid` to `DataGridInterface`.
- Change `RendererManager::__construct(DataGrid $grid)` to `__construct(DataGridInterface $grid)`.
- Update the `use` import to `DataGridInterface`.

### Post-Implementation: PHPStan Level Evaluation

After clearing all 14 errors at level 6, run `composer analyze` at level 7 and level 8 to preview new errors. Document findings but do not fix level 7+ issues in this plan — those become the next planning cycle's input.

## Rationale

- **Base class approach for `HeaderRow`/`MergedRow`:** Consolidating `getGrid()` and `isSelectable()` in `BaseGridRow` follows the existing pattern where shared behavior lives in abstract bases. It ensures any future row type automatically gets these methods.
- **`RowManager` injection via `registerRow()`:** Every row already passes through `registerRow()`, making it the ideal injection point. No constructor signature changes needed for `HeaderRow` or `MergedRow`.
- **Template method for page-jump:** The two implementations differ only in CSS wrapping. A hook method (`createPageJumpContainer`) isolates the variation, keeping the shared JS logic in one place and preventing drift.
- **`JSON_THROW_ON_ERROR`:** Makes encoding failures explicit rather than silent. At level 8+, PHPStan flags `json_encode(): string|false` — this flag narrows the return type to `string`.
- **`RendererManager` inversion:** Completes the pattern started in `RowManager` (WP-002 of the synthesis rework). The renderer constructors already accept the interface; the manager is the last holdout.

## Detailed Steps

### Step 1 — `BaseGridRow` Interface Implementation

1. In `BaseGridRow`, add:
   - `use AppUtils\Grids\DataGridInterface;`
   - `use AppUtils\Grids\DataGridException;`
   - A private nullable property: `private ?RowManager $manager = null;`
   - A setter: `public function setRowManager(RowManager $manager): self`
   - Implementation of `getGrid(): DataGridInterface` — delegates to `$this->manager->getGrid()`, throwing `DataGridException::ERROR_NO_ROW_MANAGER` if null.
   - Implementation of `isSelectable(): bool` — returns `isset($this->manager) ? $this->manager->getGrid()->hasActions() : false`.

2. In `DataGridException`, add constant: `public const ERROR_NO_ROW_MANAGER = 171702;`

3. In `RowManager::registerRow()`, call `$row->setRowManager($this)` before appending.

4. In `RowManager::getHeaderRow()`, ensure the `HeaderRow` is also registered or has `setRowManager()` called. Verify how `HeaderRow` is currently created — it may need explicit manager injection.

5. In `StandardRow`:
   - Remove the `getGrid()` method (inherited from `BaseGridRow`).
   - Remove the `isSelectable()` method (inherited from `BaseGridRow`).
   - `StandardRow` already receives `RowManager` in its constructor and calls `$manager->registerRow($this)` — verify this flow still works with the base class setter.

6. Run `composer analyze` — expect the 4 `HeaderRow`/`MergedRow` errors to be gone.

### Step 2 — `RowManager` Type Annotations

1. Add PHPDoc `@param array<int, array<string, mixed>> $rows` to `addArrays()`.
2. Add PHPDoc `@param array<string, mixed> $columnValues` to `addArray()`.
3. Add native type hint `string|StringableInterface|NULL` to the `$content` parameter of `addMerged()`.
4. Add the `use AppUtils\Interfaces\StringableInterface;` import if not present.
5. Run `composer analyze` — expect the 3 `RowManager` errors to be gone.

### Step 3 — `json_encode()` Hardening

1. In `BaseGridRenderer::createPageJumpInput()`, change both `json_encode(...)` calls to `json_encode(..., JSON_THROW_ON_ERROR)`.
2. In `Bootstrap5Renderer::createBootstrapPageJumpInput()`, change both `json_encode(...)` calls to `json_encode(..., JSON_THROW_ON_ERROR)`.
3. Run `composer test` — all 47 tests should pass.

### Step 4 — Extract Page-Jump Template Method

1. In `BaseGridRenderer`, add a new protected method:
   ```php
   protected function createPageJumpContainer(HTMLTag $input, HTMLTag $button): HTMLTag
   {
       return HTMLTag::create('span')
           ->appendContent($input)
           ->appendContent($button);
   }
   ```
2. Refactor `BaseGridRenderer::createPageJumpInput()` to call `$this->createPageJumpContainer($input, $button)` as its return value instead of building the container inline.
3. In `Bootstrap5Renderer`, override `createPageJumpContainer()` to apply the Bootstrap CSS classes:
   ```php
   protected function createPageJumpContainer(HTMLTag $input, HTMLTag $button): HTMLTag
   {
       $input->addClasses(['form-control', 'form-control-sm'])
           ->attr('style', 'width:80px');
       $button->addClasses(['btn', 'btn-sm', 'btn-outline-secondary']);

       return HTMLTag::create('div')
           ->addClasses(['d-flex', 'align-items-center', 'gap-2', 'mt-2'])
           ->appendContent($input)
           ->appendContent($button);
   }
   ```
4. Remove `Bootstrap5Renderer::createBootstrapPageJumpInput()` entirely.
5. Update `Bootstrap5Renderer::renderPaginationRow()` to call the inherited `$this->createPageJumpInput($pagination)` instead of `$this->createBootstrapPageJumpInput($pagination)`.
6. Run `composer test` — all 47 tests should pass.

### Step 5 — Dead Code & Test Rename

1. Rename `SelectionCellTest::test_renderContent_emptyValue` to `test_getSelectionCell_throwsOnMissingValueColumn`.
2. Update the PHPDoc on the method to reflect it tests `StandardRow::getSelectionCell()`.
3. Verify no dead empty-value branch exists in `SelectionCell::renderContent()` (confirmed removed — no action needed).
4. Run `composer test` — all 47 tests should pass.

### Step 6 — `RendererManager` Dependency Inversion

1. In `RendererManager.php`:
   - Change `use AppUtils\Grids\DataGrid;` to `use AppUtils\Grids\DataGridInterface;`.
   - Change property: `private DataGrid $grid;` → `private DataGridInterface $grid;`.
   - Change constructor: `public function __construct(DataGrid $grid)` → `public function __construct(DataGridInterface $grid)`.
2. Verify `selectByClass()` still works: it passes `$this->grid` to `new $class(...)`. Since `BaseGridRenderer` accepts `DataGridInterface`, this is compatible.
3. Verify `DataGrid::renderer()` still works: it passes `$this` (which implements `DataGridInterface`) to the `RendererManager` constructor.
4. Run `composer analyze` and `composer test`.

### Step 7 — PHPStan Level Evaluation

1. Run `composer analyze` — expect **0 errors** at level 6.
2. Temporarily set PHPStan to level 7 and run. Document the new errors.
3. Temporarily set PHPStan to level 8 and run. Document the new errors.
4. Restore PHPStan to level 6. Record findings as a recommendation for the next planning cycle.

### Step 8 — Manifest Updates

Update the following manifest documents to reflect all changes:

1. **`api-surface.md`:**
   - `BaseGridRow`: add `setRowManager()`, `getGrid()`, `isSelectable()` methods.
   - `DataGridException`: add `ERROR_NO_ROW_MANAGER` constant.
   - `RowManager`: update `addArrays()`, `addArray()`, `addMerged()` signatures with types, and note `setRowManager()` call in `registerRow()`.
   - `RendererManager`: update constructor/property to `DataGridInterface`.
   - `StandardRow`: remove `getGrid()` and `isSelectable()` (now inherited).
   - `BaseGridRenderer`: add `createPageJumpContainer()` method.
   - `Bootstrap5Renderer`: remove `createBootstrapPageJumpInput()`, add `createPageJumpContainer()` override.

2. **`constraints.md`:**
   - Update test table: rename `test_renderContent_emptyValue` → `test_getSelectionCell_throwsOnMissingValueColumn` in `SelectionCellTest` description.
   - Add PHPStan level evaluation results if level is upgraded.
   - Remove the pre-existing error count (should be 0).

3. **`data-flows.md`:**
   - Update row registration flow to mention `setRowManager()` injection in `registerRow()`.

4. **`file-tree.md`:**
   - Update test descriptions if significantly changed.

## Dependencies

- Steps 1 and 2 are independent and can run in parallel.
- Step 3 must come before Step 4 (the `json_encode()` hardening should be in place before the template method extraction so the extracted code already contains the fix).
- Step 4 depends on Step 3.
- Step 5 is independent of all other steps.
- Step 6 is independent of Steps 1–5.
- Step 7 depends on Steps 1–6 all being complete.
- Step 8 depends on all preceding steps.

## Required Components

### Modified Files
- `src/Grids/Rows/BaseGridRow.php` — add `setRowManager()`, `getGrid()`, `isSelectable()`
- `src/Grids/DataGridException.php` — add `ERROR_NO_ROW_MANAGER` constant
- `src/Grids/Rows/RowManager.php` — type annotations, `setRowManager()` call in `registerRow()`
- `src/Grids/Rows/Types/StandardRow.php` — remove `getGrid()`, `isSelectable()` (now inherited)
- `src/Grids/Renderer/BaseGridRenderer.php` — `json_encode()` hardening, `createPageJumpContainer()` extraction
- `src/Grids/Renderer/Types/Bootstrap5Renderer.php` — remove `createBootstrapPageJumpInput()`, add `createPageJumpContainer()` override, `json_encode()` hardening
- `src/Grids/Renderer/RendererManager.php` — `DataGrid` → `DataGridInterface`
- `tests/Cells/SelectionCellTest.php` — rename test method
- `docs/agents/project-manifest/api-surface.md` — reflect API changes
- `docs/agents/project-manifest/constraints.md` — update test table, error counts
- `docs/agents/project-manifest/data-flows.md` — update row registration
- `docs/agents/project-manifest/file-tree.md` — update test descriptions

### No New Files

## Assumptions

- `RowManager::registerRow()` is the single entry point for all row registration (including `HeaderRow`). If `HeaderRow` bypasses `registerRow()`, the manager injection must be added at its creation point.
- The `RendererManager::selectByClass()` method creates renderers via `new $class($this->grid)` — changing `$grid` to `DataGridInterface` is safe because all renderer constructors accept `DataGridInterface`.
- No external consumers depend on `RendererManager` accepting the concrete `DataGrid` type.

## Constraints

- All new code must declare `strict_types=1`.
- No constructor property promotion (Convention #11).
- Nullable property guards must use `isset()` (Convention #12).
- Fluent setters must return `$this`/`self`.
- HTML output through `HTMLTag` only (Convention #8).
- Run `composer dump-autoload` only if files are added/renamed/moved (classmap autoloading).

## Out of Scope

- Fixing the 3 `BaseGridColumn.php` PHPStan errors (stub methods `useCallbackSorting`, `useManualSorting`, and `$sortable` property — these are pre-existing stubs, not interface compliance issues).
- Fixing the 3 `DataGrid.php` PHPStan errors (`new static()` warning, stub `getSortColumn()`, stub `getSortDir()` — these are documented stubs).
- Fixing the 1 `GridForm.php` PHPStan error (PHPDoc/native type mismatch — pre-existing, unrelated).
- The namespace anomaly (`WebcomicsBuilder` in `StandardRow`).
- Actually raising the PHPStan level beyond 6 (Step 7 is evaluation only).
- Audit of all remaining `trigger_error()` calls (none exist — confirmed cleared by synthesis).

## Acceptance Criteria

1. `composer analyze` reports **7 errors** (down from 14 — the 7 fixable errors are resolved; the 7 pre-existing stubs/design issues in `BaseGridColumn`, `DataGrid`, and `GridForm` remain).
2. `composer test` reports **47 tests, all passing** (test count unchanged; one test renamed).
3. `HeaderRow` and `MergedRow` fully implement `GridRowInterface` — no PHPStan "abstract method" errors.
4. `RowManager::addArrays()`, `addArray()`, and `addMerged()` have complete type annotations — no PHPStan "no type specified" errors.
5. All `json_encode()` calls in renderer page-jump methods use `JSON_THROW_ON_ERROR`.
6. Only one page-jump input builder exists in `BaseGridRenderer`; `Bootstrap5Renderer` customizes via `createPageJumpContainer()` override only.
7. `RendererManager` depends on `DataGridInterface`, not `DataGrid`.
8. `SelectionCellTest::test_getSelectionCell_throwsOnMissingValueColumn` exists (renamed from `test_renderContent_emptyValue`).
9. All manifest documents are updated to reflect the changes.

## Testing Strategy

- **Unit tests:** Run `composer test` after each step. All 47 tests must remain green throughout.
- **Static analysis:** Run `composer analyze` after Steps 1, 2, and 6 to verify progressive error reduction.
- **Regression:** No new PHPStan errors introduced. No test failures.
- **Level evaluation:** Run PHPStan at levels 7 and 8 (Step 7) for informational purposes only.

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| **`HeaderRow` is created outside `registerRow()`** | Step 1.4 explicitly verifies and patches the `getHeaderRow()` creation path to ensure manager injection. |
| **`StandardRow` relies on constructor `RowManager` access that conflicts with base class** | `StandardRow` already stores `$manager` and calls `registerRow($this)` — the base class setter is an additional, non-conflicting injection path. Verify in StandardRow's constructor that `registerRow` is called, which triggers the setter. |
| **`RendererManager::selectByClass()` breaks with interface type** | All renderers accept `DataGridInterface` in their constructors. Verified by reading `BaseGridRenderer::__construct()`. |
| **Template method extraction changes HTML output** | The `createPageJumpContainer()` hook produces identical HTML for each renderer. Manually verify rendered output in examples before/after. |
| **`isSelectable()` semantic change for `MergedRow`/`HeaderRow`** | Base implementation returns `false` when no manager is set, and delegates to `hasActions()` otherwise. `MergedRow` and `HeaderRow` are never selectable in the rendering pipeline — the renderers only call `isSelectable()` on `StandardRow`. Safe default. |
