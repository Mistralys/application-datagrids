# Synthesis Report — 2026-03-13 Sorting Rework

**Plan:** `2026-03-13-synthesis-rework`
**Date:** 2026-03-13
**Status:** COMPLETE
**Work Packages:** 8 / 8 COMPLETE
**Pipeline Health:** All stages PASS across all WPs

---

## Executive Summary

This cycle addressed a collection of code-quality, correctness, and test-coverage deficiencies carried over from the prior `2026-03-12-column-sorting` plan. Eight work packages were completed, spanning interface hardening, bug fixing, renderer refactoring, new test suites, security documentation, and a comprehensive manifest update pass.

The codebase exits this cycle with a closed interface contract for the sorting subsystem, a fixed stale-callback bug in `BaseGridColumn`, a cleaner renderer extension model, two new purpose-built test classes, and fully consistent project manifest documentation.

---

## Metrics

| Metric | Value |
|---|---|
| Total tests | 78 |
| Total assertions | 179 |
| Tests failed | 0 |
| PHPStan level | 6 |
| PHPStan errors | 0 |
| PHPStan files analysed | 44 |
| Security issues | 0 |
| New source files | 0 |
| New test files | 2 |
| Source files modified | 4 |
| Manifest files modified | 5 |

### Test Suite Breakdown

| Suite | Tests | Assertions |
|---|---|---|
| Pagination (GridPaginationTest + ArrayPaginationTest) | 33 | — |
| Actions (GridActionsTest) | 7 | — |
| Rows (StandardRowTest) | 5 | — |
| Cells (SelectionCellTest) | 2 | — |
| Sorting (ColumnSortingTest 18 + SortManagerTest 8 + RendererSortHeaderTest 5) | 31 | — |
| **Total** | **78** | **179** |

---

## Work Package Summary

### WP-001 — SortManager Interface Contract
**Files:** `src/Grids/Sorting/SortManagerInterface.php`, `src/Grids/DataGrid.php`

Added `sortRows(array &$rows): void` to `SortManagerInterface`. Changed `DataGrid::$sortManager` from the concrete `?SortManager` type to `?SortManagerInterface`. Removed the `use` import for `SortManager` in `DataGrid.php`; the single instantiation site now uses the FQN. The interface contract is now fully closed — custom sort managers can be injected, and mock substitution in tests is possible.

### WP-002 — Stale Callback Bug Fix
**File:** `src/Grids/Columns/BaseGridColumn.php`

One-line fix in each of `useNativeSorting()` and `useManualSorting()`: `$this->sortCallback = null` before the `return`. Eliminates the stale-callback footgun where switching sort modes after calling `useCallbackSorting()` would leave a dead callback silently active.

### WP-003 — Renderer Refactoring (createSortAnchor)
**Files:** `src/Grids/Renderer/BaseGridRenderer.php`, `src/Grids/Renderer/Types/Bootstrap5Renderer.php`

Replaced the ad-hoc `createSortLink()` helper (removed entirely) with the structured `createSortAnchor(GridColumnInterface $column, array $extraClasses = []): HTMLTag` API. `Bootstrap5Renderer` now overrides `createSortAnchor()` — not `createHeaderCell()` — injecting its 5 framework classes via `array_merge()` before delegating fully to the parent. `$this->grid->sorting()` is called exactly once per method body (extracted to `$sorting`), removing the previous double-call pattern.

### WP-004 — SortManagerTest (new test class)
**File:** `tests/Sorting/SortManagerTest.php`

8 test methods, 49 assertions covering: Native ASC, Native DESC, Callback ASC, Callback DESC, Manual no-op, no-sort-column no-op, `MergedRow` position preservation, and numeric vs. lexicographic sort. `setUp/tearDown` isolates `$_GET` state. Tests use static closures for callbacks and the `DataGridInterface::SORT_DESC` constant for direction.

### WP-005 — RendererSortHeaderTest (new test class)
**File:** `tests/Sorting/RendererSortHeaderTest.php`

5 test methods, 16 assertions: non-sortable column (no link), sortable column (has link), active-sort column (has indicator), Bootstrap5 sortable (has Bootstrap classes), Bootstrap5 non-sortable (no link). `setUp/tearDown` saves/restores both `$_GET` and `$_SERVER['REQUEST_URI']`.

### WP-006 — getSortURL Security Documentation
**Files:** `src/Grids/Sorting/SortManager.php`, `docs/agents/plans/2026-03-12-column-sorting/work/WP-004.md`

Added `@return` docblock to `SortManager::getSortURL()` with the XSS safety note: *"When used outside HTMLTag contexts, callers must apply `htmlspecialchars()` to prevent XSS."* Also corrected a namespace typo in the prior-plan WP-004.md (`AppUtils\Grids\Tests\Sorting` → `AppUtils\Tests\Sorting`).

### WP-007 — Manifest Consistency Pass
**Files:** `docs/agents/project-manifest/api-surface.md`, `constraints.md`, `file-tree.md`, `AGENTS.md`

Updated all four manifest documents to reflect the WP-001–WP-006 changes: `sortRows()` and `createSortAnchor()` documented in `api-surface.md`; test count updated to 78 (with full breakdown) in `constraints.md`, `file-tree.md`, and `AGENTS.md`; both new test files listed in `file-tree.md`; all prior tech-debt and bug entries resolved in `constraints.md`. All documents cross-checked for internal consistency.

### WP-008 — Final Validation + Follow-up Fixes
**Files:** `docs/agents/project-manifest/constraints.md`, `src/Grids/Sorting/SortManagerInterface.php`

Independent toolchain validation: `composer dump-autoload` (44 files, clean), `composer analyze` (0 errors, 44 files, level 6), `composer test` (78/78, 179 assertions). Addressed two follow-up items flagged during code review: mirrored the `@return` XSS note from `SortManager::getSortURL()` onto `SortManagerInterface::getSortURL()`, and added test file references (`ColumnSortingTest.php`, `SortManagerTest.php`) to the namespace anomaly inventory in `constraints.md`.

---

## Strategic Recommendations ("Gold Nuggets")

### 1. Clean Up Raw instanceof Guard in ColumnSortingTest
**Priority: Low | Source: WP-001 Implementation**

`ColumnSortingTest.php` casts `$grid->sorting()` to `SortManager` with an `instanceof` check before calling `sortRows()`. Now that `sortRows()` is declared on `SortManagerInterface`, this guard is redundant. Updating the test to call `sortRows()` directly on the interface-typed return value would better express intent and remove the concrete type coupling.

### 2. Accessibility: Sort Indicator Span Needs aria-hidden
**Priority: Medium | Source: WP-003 Code Review**

The sort direction indicator `<span>` in `BaseGridRenderer::createSortAnchor()` uses raw UTF-8 ▲/▼ characters but carries no `aria-hidden="true"`. Screen readers will announce the raw Unicode glyph names ("black up-pointing triangle"). A follow-up accessibility pass should add `aria-hidden="true"` to the indicator span and optionally add a visually hidden `<span class="sr-only">` with a meaningful label (e.g., "sorted ascending").

### 3. RendererSortHeaderTest: Add SORT_DESC and Multi-Column Cases
**Priority: Low | Source: WP-005 QA + Code Review**

Two coverage gaps remain in `RendererSortHeaderTest`:
- `test_activeSortColumn_hasIndicator` only exercises `SORT_ASC`; a companion `SORT_DESC` test would cover the `▼` branch in `BaseGridRenderer::createSortAnchor()`.
- No test verifies that only the **active** column shows a direction indicator when a grid has multiple sortable columns (inactive sortable columns should show plain sort links).

### 4. Consider Splitting api-surface.md into Public API vs. Protected Extension Points
**Priority: Low | Source: WP-007 Code Review**

`api-surface.md` currently documents `protected` helpers (e.g., `createSortAnchor()`, `createHeaderCell()`) alongside the public API. As the renderer hierarchy grows, separating the document into a **Public API** section and a **Protected Extension Points** section would reduce consumer confusion and make the intended extension model explicit.

### 5. WebcomicsBuilder Namespace Anomaly — Enumerate Test File Scope
**Priority: Low | Source: WP-008 Code Review (now resolved)**

The namespace anomaly (`WebcomicsBuilder\Grids\Rows\Types\StandardRow`) was previously documented only for `src/` files. WP-008 Documentation resolved this by adding `ColumnSortingTest.php` and `SortManagerTest.php` to the anomaly inventory in `constraints.md`. When this anomaly is eventually resolved, all affected files (source and test) are now enumerated.

---

## Blockers / Failures

None. All 8 WPs completed with PASS pipelines at every stage. No regressions introduced.

---

## Next Steps for Planner / Project Manager

1. **Accessibility pass** — Address the sort indicator `aria-hidden` gap (item #2 above); low effort, high-impact for accessibility compliance.
2. **RendererSortHeaderTest expansion** — Add SORT_DESC and multi-column active-indicator tests (item #3 above).
3. **ColumnSortingTest cleanup** — Remove the stale `instanceof` guard (item #1 above); makes the purpose of `SortManagerInterface` visible in test code.
4. **Namespace anomaly resolution** — The `WebcomicsBuilder` namespace anomaly remains the largest technical-debt item; all affected files are now fully documented in `constraints.md`.
5. **api-surface.md restructure** — Low-priority documentation improvement; consider when the renderer class tree expands.
