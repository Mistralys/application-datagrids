# Plan — Sorting Synthesis Rework

## Summary

Address all actionable items surfaced in the `2026-03-12-column-sorting` synthesis and the remaining open item from the `2026-03-12-synthesis-followup` synthesis. The changes span four themes: (1) close the `SortManagerInterface` contract gap, (2) clear stale sort callback state on mode change, (3) reduce structural duplication in the Bootstrap5 sort header rendering, and (4) add missing automated tests for `SortManager::sortRows()` and sort-aware header cell rendering. Several low-priority cosmetic fixes (local variable extraction, `@return` docblock safety note, spec doc namespace typo) are bundled in as well.

## Architectural Context

### Sorting subsystem
- `SortManagerInterface` (`src/Grids/Sorting/SortManagerInterface.php`) — public contract exposing state queries and URL building, but currently **missing** `sortRows()`.
- `SortManager` (`src/Grids/Sorting/SortManager.php`) — concrete implementation; `sortRows()` exists here but is not on the interface.
- `DataGrid` (`src/Grids/DataGrid.php`) — holds `private ?SortManager $sortManager` (concrete type) and calls `$this->sortManager->sortRows($rows)` directly at line 82; `sorting()` returns `SortManagerInterface`.

### Column sort API
- `BaseGridColumn` (`src/Grids/Columns/BaseGridColumn.php`) — `useNativeSorting()`, `useCallbackSorting()`, `useManualSorting()` set `$sortMode` but only `useCallbackSorting()` touches `$sortCallback`. The other two leave a stale callback in place.

### Renderer sort headers
- `BaseGridRenderer::createHeaderCell()` (lines 155–177) — builds the `<th>`, delegates to `createSortLink()` for sortable columns.
- `BaseGridRenderer::createSortLink()` (lines 179–194) — creates `<a>` with sort URL and optional indicator. Calls `$this->grid->sorting()` three times without caching.
- `Bootstrap5Renderer::createHeaderCell()` (lines 58–91) — duplicates ~80% of the base `<th>` construction and re-implements the `<a>` link with Bootstrap classes, calling `$this->grid->sorting()` three times as well.

### Test suites
- `tests/Sorting/ColumnSortingTest.php` — 18 tests (51 assertions) covering column flags, sort state resolution, row sorting (cursory), and URL toggling.
- No `SortManagerTest.php` or `RendererSortHeaderTest.php` exist.

### Pagination side-effect pattern (prior synthesis)
- `Bootstrap5Renderer::createPageJumpContainer()` (lines 222–235) — mutates `$input` and `$button` HTMLTag objects received from the base class, then wraps them. Functional but couples the hook to the caller's object graph. **Deferred** — this is a low-priority design issue; the sorting header refactor is the higher-impact change in this area.

## Approach / Architecture

### 1. Close the `SortManagerInterface` contract
Add `sortRows(array &$rows): void` to `SortManagerInterface`. Update `DataGrid::$sortManager` from `?SortManager` to `?SortManagerInterface` and remove the concrete import. This enables full substitution/mocking.

### 2. Sort callback cleanup
In `BaseGridColumn::useNativeSorting()` and `useManualSorting()`, add `$this->sortCallback = null;` to prevent stale callback residue when switching modes.

### 3. Refactor Bootstrap5 sort header
Extract a `protected createSortAnchor(GridColumnInterface $column, array $extraClasses = []): HTMLTag` method in `BaseGridRenderer` that encapsulates the `<a>` link (href, label, indicator). `createSortLink()` calls `createSortAnchor([], ...)`. `Bootstrap5Renderer::createHeaderCell()` calls `parent::createHeaderCell()` after customizing via `createSortAnchor()` override — or, more simply, overrides `createSortAnchor()` to add the Bootstrap classes, eliminating the duplicated `<th>` construction entirely.

Additionally, extract `$sorting = $this->grid->sorting()` as a local variable in both `createSortLink()` (base) and the refactored Bootstrap5 override to reduce repeated accessor calls.

### 4. New test suites
- **`tests/Sorting/SortManagerTest.php`** — covers `sortRows()` for Native, Callback, and Manual modes; ASC/DESC direction; callback+DESC negation; non-standard row (MergedRow) position preservation.
- **`tests/Sorting/RendererSortHeaderTest.php`** — covers `createHeaderCell()` HTML output for sortable vs non-sortable columns, active sort indicator presence, and Bootstrap5 class injection.

### 5. Docblock & spec fixes
- Add a `@return` note on `SortManager::getSortURL()` warning that output must be HTML-escaped outside `HTMLTag` contexts.
- Fix the namespace in `docs/agents/plans/2026-03-12-column-sorting/work/WP-004.md` from `AppUtils\Grids\Tests\Sorting` to `AppUtils\Tests\Sorting`.

## Rationale

- **Interface completeness** (#1) is the highest-priority item: `sortRows()` on the interface fixes the concrete-type coupling in `DataGrid`, enables mocking in tests, and aligns with the project's interface-driven architecture convention.
- **Callback cleanup** (#2) is a one-line fix per method that prevents a subtle footgun; it should ship with the interface change since both touch the sorting subsystem.
- **Header refactor** (#3) eliminates ~80% structural duplication between two renderers and makes adding future renderers easier. The local-variable extraction is bundled because it affects the same methods.
- **Tests** (#4) are the highest-volume item; without them, the sorting subsystem's core logic (`sortRows()`) has no regression safety net beyond code review.
- **Docblock/spec fixes** (#5) are trivial but prevent future confusion.

## Detailed Steps

### Step 1 — Close `SortManagerInterface` contract
1. Add `public function sortRows(array &$rows): void;` to `SortManagerInterface` with a `@param GridRowInterface[] $rows` PHPDoc.
2. Change `DataGrid::$sortManager` from `private ?SortManager $sortManager = null` to `private ?SortManagerInterface $sortManager = null`.
3. Remove the `use AppUtils\Grids\Sorting\SortManager;` import from `DataGrid.php`.
4. Keep the lazy instantiation `new SortManager($this)` in `sorting()` — the concrete class is only needed at the construction site.
5. Run `composer analyze` — expect 0 errors.

### Step 2 — Sort callback cleanup in `BaseGridColumn`
1. In `useNativeSorting()`, add `$this->sortCallback = null;` before the return.
2. In `useManualSorting()`, add `$this->sortCallback = null;` before the return.

### Step 3 — Refactor Bootstrap5 sort header & local variable extraction
1. In `BaseGridRenderer::createSortLink()`, extract `$sorting = $this->grid->sorting();` and replace all three `$this->grid->sorting()` calls.
2. Rename `createSortLink()` to `createSortAnchor()` and add an `array $extraClasses = []` parameter. Apply `$extraClasses` to the `<a>` element via `addClasses()`.
3. Replace `Bootstrap5Renderer::createHeaderCell()` with an override that simply calls `parent::createHeaderCell($column)` for non-sortable columns (already does this), and for sortable columns, override `createSortAnchor()` instead — adding the Bootstrap utility classes (`text-decoration-none`, `text-reset`, `d-inline-flex`, `align-items-center`, `gap-1`).
4. Delete the duplicated `<th>` construction code from `Bootstrap5Renderer::createHeaderCell()`.
5. Extract `$sorting = $this->grid->sorting();` in the Bootstrap5 override as well if it still has direct calls.
6. Run `composer analyze` — expect 0 errors.

### Step 4 — `SortManagerTest.php`
Create `tests/Sorting/SortManagerTest.php` with the following test methods:
1. `test_sortRows_nativeAsc` — native sorting on a string column, ASC direction. Verify row order.
2. `test_sortRows_nativeDesc` — native sorting, DESC direction. Verify reversed order.
3. `test_sortRows_callbackAsc` — callback sorting with a custom comparator, ASC.
4. `test_sortRows_callbackDesc` — callback sorting, DESC — verify the negation behavior.
5. `test_sortRows_manual_noOp` — manual sort mode does not reorder rows.
6. `test_sortRows_noSortColumn_noOp` — no sort column active → rows unchanged.
7. `test_sortRows_preservesMergedRowPositions` — MergedRow instances remain at their original indices after sort.
8. `test_sortRows_numericNativeSorting` — native sort on integer column; verify numeric comparison is used.

Use `$_GET` manipulation (save/restore in `setUp`/`tearDown`) to control sort state, following the `ArrayPaginationTest` pattern.

### Step 5 — `RendererSortHeaderTest.php`
Create `tests/Sorting/RendererSortHeaderTest.php` with:
1. `test_nonSortableColumn_noLink` — non-sortable column produces `<th>` with plain text, no `<a>`.
2. `test_sortableColumn_hasLink` — sortable column produces `<th>` containing an `<a>` element.
3. `test_activeSortColumn_hasIndicator` — active sort column's `<a>` contains an indicator span (▲ or ▼).
4. `test_bootstrap5_sortableColumn_hasBootstrapClasses` — Bootstrap5 renderer adds the expected utility classes to the `<a>`.
5. `test_bootstrap5_nonSortable_noLink` — Bootstrap5 renderer produces plain `<th>` for non-sortable columns.

Use `renderHeaderCell()` on each renderer type to capture output and assert DOM structure.

### Step 6 — Docblock & spec fixes
1. Add `@return string The sort URL. When used outside HTMLTag contexts, callers must apply htmlspecialchars() to prevent XSS.` to `SortManager::getSortURL()`.
2. In `docs/agents/plans/2026-03-12-column-sorting/work/WP-004.md`, change `AppUtils\Grids\Tests\Sorting` to `AppUtils\Tests\Sorting`.

### Step 7 — Manifest updates
1. **`api-surface.md`** — Add `sortRows()` to `SortManagerInterface` section. Update `BaseGridRenderer` to show `createSortAnchor()` instead of `createSortLink()`. Note the `$extraClasses` parameter. Remove `Bootstrap5Renderer::createHeaderCell()` override if it is fully replaced by `createSortAnchor()` override.
2. **`constraints.md`** — Remove the `BaseGridColumn::getSortCallback()` tech debt entry (resolved by callback cleanup). Update test counts.
3. **`file-tree.md`** — Add `SortManagerTest.php` and `RendererSortHeaderTest.php` entries under `tests/Sorting/`.
4. **`AGENTS.md`** — Update total test count (65 → new total after adding tests).

### Step 8 — Final validation
1. `composer analyze` — 0 errors at level 6.
2. `composer test` — all tests pass.

## Dependencies

- Steps 1–2 are independent and can be done in parallel.
- Step 3 depends on Step 1 being done (refactored code references `SortManagerInterface`).
- Steps 4–5 (test writing) depend on Steps 1–3 being complete (tests should exercise the refactored code).
- Step 6 is independent of all other steps.
- Step 7 depends on Steps 1–5 (manifest reflects final state).
- Step 8 depends on all other steps.

## Required Components

### Modified files
- `src/Grids/Sorting/SortManagerInterface.php` — add `sortRows()`
- `src/Grids/Sorting/SortManager.php` — add `@return` docblock to `getSortURL()`
- `src/Grids/DataGrid.php` — change `$sortManager` type to interface
- `src/Grids/Columns/BaseGridColumn.php` — clear callback in native/manual setters
- `src/Grids/Renderer/BaseGridRenderer.php` — rename `createSortLink()` → `createSortAnchor()`, add `$extraClasses` param, extract local `$sorting`
- `src/Grids/Renderer/Types/Bootstrap5Renderer.php` — replace `createHeaderCell()` override with `createSortAnchor()` override
- `docs/agents/plans/2026-03-12-column-sorting/work/WP-004.md` — fix namespace

### New files
- `tests/Sorting/SortManagerTest.php`
- `tests/Sorting/RendererSortHeaderTest.php`

### Updated documentation
- `docs/agents/project-manifest/api-surface.md`
- `docs/agents/project-manifest/constraints.md`
- `docs/agents/project-manifest/file-tree.md`
- `AGENTS.md`

## Assumptions

- The existing `ColumnSortingTest` tests continue to pass without modification; the refactored API is backward-compatible at the protected level.
- `createSortLink()` is not called from outside the renderer hierarchy (it is `protected`); renaming to `createSortAnchor()` is safe.
- The `createPageJumpContainer()` side-effect pattern (synthesis-followup Rec #5) is **deferred** — it is functional, low-priority, and does not interact with the sorting changes.
- The `StandardRow` duplicate `$manager` property (synthesis-followup Rec #1) has already been resolved and requires no action.
- The `GridForm` null-handling issues (synthesis-followup Recs #2, #3, #6) have already been resolved and require no action.

## Constraints

- All new PHP files must include `declare(strict_types=1);`.
- All new setter methods must return `self`/`$this` (fluent API).
- All HTML output must use `HTMLTag` — no raw string concatenation.
- `composer dump-autoload` must be run after creating new test files (classmap autoloading).
- New traits must have matching interfaces (not applicable here).
- The `WebcomicsBuilder` namespace anomaly must not be "fixed" unless explicitly requested.

## Out of Scope

- `Bootstrap5Renderer::createPageJumpContainer()` side-effect mutation pattern (synthesis-followup Rec #5) — deferred, low priority, functional as-is.
- PHPStan level 8 upgrade (already at 0 errors per constraints.md).
- Sorting feature extensions (new sort modes, multi-column sort, etc.).
- Fixing the `WebcomicsBuilder` namespace anomaly.

## Acceptance Criteria

1. `SortManagerInterface` declares `sortRows(array &$rows): void`.
2. `DataGrid::$sortManager` is typed as `?SortManagerInterface`, not `?SortManager`.
3. `BaseGridColumn::useNativeSorting()` and `useManualSorting()` set `$this->sortCallback = null`.
4. `BaseGridRenderer` exposes `createSortAnchor()` with an `$extraClasses` parameter; the old `createSortLink()` method no longer exists.
5. `Bootstrap5Renderer` overrides `createSortAnchor()` (not `createHeaderCell()`) to inject Bootstrap classes.
6. `$this->grid->sorting()` is called at most once per method body (extracted to local variable).
7. `SortManagerTest` has ≥ 8 test methods covering Native/Callback/Manual modes, ASC/DESC, MergedRow preservation, and no-sort-column no-op.
8. `RendererSortHeaderTest` has ≥ 5 test methods covering both renderers and sortable/non-sortable/active-sort scenarios.
9. `SortManager::getSortURL()` has a `@return` docblock safety note.
10. All existing 65 tests continue to pass.
11. `composer analyze` reports 0 errors at level 6.
12. All manifest documents are up to date.

## Testing Strategy

- **Unit tests**: Two new test classes (`SortManagerTest`, `RendererSortHeaderTest`) with ≥ 13 new test methods total.
- **Regression**: Full `composer test` run after all changes — all 65+ tests must pass.
- **Static analysis**: `composer analyze` at level 6 — 0 errors.
- **Manual smoke test**: Run `examples/4-pagination.php` to verify sort links render and function correctly in both renderers.

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| **Renaming `createSortLink()` breaks subclasses** | Method is `protected` and only called within the renderer hierarchy. Grep the codebase for all call sites before renaming. No external consumers exist (library is WIP). |
| **`Bootstrap5Renderer::createHeaderCell()` removal may miss edge cases** | The refactored `createSortAnchor()` override must produce identical HTML output. Verify with `RendererSortHeaderTest` assertions on actual markup. |
| **`$_GET` manipulation in tests leaks state** | Follow existing `ArrayPaginationTest` pattern: save `$_GET` in `setUp()`, restore in `tearDown()`. |
| **Interface change breaks downstream consumers** | Library is WIP with no known external consumers. The change is additive (new method on interface). |
