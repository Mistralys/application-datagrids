# Project Status Report — 2026-03-12 Synthesis Follow-Up

**Date:** 2026-03-12  
**Plan:** `2026-03-12-synthesis-followup`  
**Status:** COMPLETE — all 8 work packages delivered  
**Pipeline health:** 8/8 WPs with all stages PASS

---

## Executive Summary

This session resolved all outstanding technical debt surfaced in the 2026-03-11 synthesis report. The primary goal — **PHPStan level 6 with zero errors** — was achieved. The codebase also received a security hardening pass, a structural refactor to the renderer hierarchy, completion of the dependency-inversion pattern throughout the rendering layer, and a comprehensive project-manifest refresh.

| Goal | Outcome |
|---|---|
| PHPStan level 6: 0 errors | **Achieved** (was 14 errors at session start) |
| Interface compliance for HeaderRow / MergedRow | **Achieved** |
| json_encode() hardening (JSON_THROW_ON_ERROR) | **Achieved** |
| Template method: page-jump builder | **Achieved** |
| RendererManager DIP | **Achieved** |
| Test clarity (SelectionCellTest rename) | **Achieved** |
| Project manifest fully current | **Achieved** |

---

## Metrics

| Metric | Value |
|---|---|
| Work Packages | 8 — all COMPLETE |
| Tests Passing | 47 / 47 (63 assertions) |
| Test Failures | 0 |
| PHPStan Level 6 Errors | **0** (was 14) |
| PHPStan Level 7 Errors | 0 (identical to level 6) |
| PHPStan Level 8 Errors | 2 (pre-existing; see Next Steps) |
| Security Issues | 0 |
| QA Bounces | 2 (WP-001 AC4, WP-007 AC1 — both resolved) |

---

## Work Package Summary

### WP-001 — BaseGridRow: Interface Compliance for HeaderRow & MergedRow

**Scope:** Eliminate 4 PHPStan errors caused by `HeaderRow` and `MergedRow` failing to implement `getGrid()` and `isSelectable()`.

**Approach:** Pushed both implementations into `BaseGridRow` with a `setRowManager()` injection point. `RowManager::registerRow()` and `RowManager::getHeaderRow()` now call `setRowManager()` on each registered row. `StandardRow` had its duplicate implementations removed.

**QA bounce (resolved):** QA correctly identified that the base-class `isSelectable()` delegates to `hasActions()`, meaning `HeaderRow` and `MergedRow` would return `true` when the grid has actions. Fix: both classes received explicit `isSelectable(): bool { return false; }` overrides.

**Files:** `DataGridException.php`, `BaseGridRow.php`, `RowManager.php`, `StandardRow.php`, `HeaderRow.php`, `MergedRow.php`

---

### WP-002 — RowManager Type Annotations (3 PHPStan errors)

Added generic PHPDoc array types to `addArrays()` (`array<int, array<string, mixed>>`), `addArray()` (`array<string, mixed>`), and a native `string|StringableInterface|NULL` type union to `addMerged()`.

**Files:** `RowManager.php`, `api-surface.md`

---

### WP-003 — json_encode() Security Hardening

Added `JSON_THROW_ON_ERROR` to all 4 `json_encode()` calls in the page-jump input builders (`BaseGridRenderer::createPageJumpInput()` ×2; `Bootstrap5Renderer::createBootstrapPageJumpInput()` ×2). Converts silent `false` returns to thrown `\JsonException` on encoding failure — the correct failure mode for a library.

**Files:** `BaseGridRenderer.php`, `Bootstrap5Renderer.php`

---

### WP-004 — Template Method: Page-Jump Builder De-duplication

Refactored the near-identical page-jump builder duplication via the Template Method pattern:

- Added `protected createPageJumpContainer(HTMLTag $input, HTMLTag $button): HTMLTag` to `BaseGridRenderer` — returns a plain `<span>`.
- `Bootstrap5Renderer` overrides `createPageJumpContainer()` to produce the Bootstrap-styled `<div class="d-flex align-items-center gap-2 mt-2">`.
- Removed `Bootstrap5Renderer::createBootstrapPageJumpInput()` entirely.
- `Bootstrap5Renderer::renderPaginationRow()` now delegates to the inherited `createPageJumpInput()`.

**Files:** `BaseGridRenderer.php`, `Bootstrap5Renderer.php`, `api-surface.md`

---

### WP-005 — SelectionCellTest: Rename Stale Test

Renamed `test_renderContent_emptyValue` → `test_getSelectionCell_throwsOnMissingValueColumn`. The new name accurately describes the test: it exercises the guard in `StandardRow::getSelectionCell()` that throws `DataGridException` when no value column is configured. PHPDoc updated to explain that the empty-value render path is no longer reachable.

**Files:** `SelectionCellTest.php`, `constraints.md`

---

### WP-006 — RendererManager Dependency Inversion

Changed `RendererManager::$grid` and its constructor parameter from concrete `DataGrid` to `DataGridInterface`. The full rendering chain (RendererManager → BaseGridRenderer → concrete renderers) is now consistently interface-typed throughout.

**Files:** `RendererManager.php`, `api-surface.md`

---

### WP-007 — PHPStan Level 6: Zero Errors (sorting stubs + miscellaneous)

**QA bounce (resolved):** First run revealed 7 remaining errors in sorting stubs and factory method not addressed by WP-001 through WP-006. Developer rework fixed all:

| File | Fix Applied |
|---|---|
| `BaseGridColumn.php` | Declared `private bool $sortable = false`; added `return $this` to both sorting stubs |
| `DataGrid.php` | Suppressed `new.static()` with `@phpstan-ignore new.static`; `getSortColumn()` now returns `null`; `getSortDir()` now returns `SORT_ASC` |
| `GridForm.php` | Stripped `|NULL` from `@param` of `addHiddenVar()` to match native type |

PHPStan level 6: **0 errors** confirmed. Level 7: 0 errors. Level 8: 2 errors (see Next Steps).

**Files:** `BaseGridColumn.php`, `DataGrid.php`, `GridForm.php`

---

### WP-008 — Project Manifest Comprehensive Update

Refreshed all four project-manifest documents against the live codebase after WP-001 through WP-007:

- **`api-surface.md`:** Already up-to-date — no edits required.
- **`constraints.md`:** Added PHPStan note — level 6 now passes with 0 errors.
- **`data-flows.md`:** Added `setRowManager()` injection documentation to the Grid Creation flow.
- **`file-tree.md`:** Corrected stale test counts (`GridPaginationTest` 20→22, `ArrayPaginationTest` 13→11, total assertions 69→63) and updated test descriptions for `SelectionCellTest` and `StandardRowTest`.

**Files:** `constraints.md`, `data-flows.md`, `file-tree.md`

---

## Blockers & Failures

None. Both QA bounces (WP-001 AC4, WP-007 AC1) were resolved within the same session with targeted rework.

---

## Strategic Recommendations (Gold Nuggets)

### 1. Duplicate `$manager` in `StandardRow` — Low Priority Technical Debt
`StandardRow` still holds a `private RowManager $manager` property alongside the one promoted to `BaseGridRow`. Both are set to the same instance. This is not a functional bug but creates maintenance confusion. Recommend removing the child copy in the next `StandardRow` refactor.

### 2. `setHiddenVars` / `addHiddenVar` Null Type Mismatch — Medium Priority
`setHiddenVars()`'s `@param` annotation still declares `array<string, string|int|bool|NULL>`, but `addHiddenVar()` no longer accepts `null` (fixed in WP-007). A caller supplying a `null` value via `setHiddenVars()` would hit a `TypeError` at runtime. **Recommended follow-up:** strip `NULL` from `setHiddenVars()` annotation and add a null-guard (or decide what "null" should mean — key removal?).

### 3. PHPStan Level 8 — 2 Known Errors
Level 8 reports 2 errors not addressed this session:
- `GridForm.php:49` — `addHiddenVar()` receives `bool|int|string|null` from `setHiddenVars` but `null` is not accepted.
- `RendererManager.php:68` — `getRenderer()` can return `null` but return type declares `GridRendererInterface`. Fix: widen return type to `GridRendererInterface|null` or guard the null case.

These are both straightforward one-file fixes and would complete the level 8 upgrade path.

### 4. Sorting Stubs — Incomplete Feature, Medium Priority
`BaseGridColumn::useCallbackSorting()`, `useManualSorting()`, `DataGrid::getSortColumn()`, and `DataGrid::getSortDir()` remain stubs (TODO bodies with safe return values). When sorting is implemented:
- All three `use*Sorting()` methods must set `$sortable = true`.
- `useCallbackSorting()` and `useManualSorting()` should return `self` (not `GridColumnInterface`) for fluent API type precision.
- `getSortColumn()` / `getSortDir()` need real implementations.

### 5. Bootstrap5Renderer Side-Effecting Mutation Pattern — Low Priority
`Bootstrap5Renderer::createPageJumpContainer()` mutates the `HTMLTag $input` and `$button` objects passed from the base method (adds CSS classes and a style attribute). The logic is correct and the scope is tight, but the design couples the hook to the caller's object graph. A future-proof refactor would pass the `GridPagination` object directly to the hook and have it build all elements independently.

### 6. Orphaned PHPDoc Comment in `GridForm::addHiddenVar()` — Low Priority
The inline comment _"Set to `null` to remove the hidden variable."_ (line ~24) was not removed when `|NULL` was stripped from the `@param` type. The method now has a strict non-nullable type; passing null throws a `TypeError`. Update the comment when this class is next touched.

---

## Next Steps

**Immediate (achievable in one focused session):**
1. Fix the `setHiddenVars`/`addHiddenVar` null mismatch (Strategic Rec #2).
2. Fix `RendererManager::getRenderer()` return type nullability (Strategic Rec #3).
3. Fix `GridForm::addHiddenVar()` PHPDoc (Strategic Rec #6).
4. Reach **PHPStan level 8: 0 errors**.

**Medium term:**
5. Remove duplicate `RowManager $manager` from `StandardRow` (Strategic Rec #1).

**Long term:**
6. Implement sorting (Strategic Rec #4) — this is the largest remaining WIP surface in the codebase.

---

## Artifacts Modified This Session

| File | WP(s) |
|---|---|
| `src/Grids/DataGridException.php` | WP-001 |
| `src/Grids/Rows/BaseGridRow.php` | WP-001 |
| `src/Grids/Rows/RowManager.php` | WP-001, WP-002 |
| `src/Grids/Rows/Types/StandardRow.php` | WP-001 |
| `src/Grids/Rows/Types/HeaderRow.php` | WP-001 |
| `src/Grids/Rows/Types/MergedRow.php` | WP-001 |
| `src/Grids/Renderer/BaseGridRenderer.php` | WP-003, WP-004 |
| `src/Grids/Renderer/Types/Bootstrap5Renderer.php` | WP-003, WP-004 |
| `src/Grids/Renderer/RendererManager.php` | WP-006 |
| `src/Grids/Columns/BaseGridColumn.php` | WP-007 |
| `src/Grids/DataGrid.php` | WP-007 |
| `src/Grids/Form/GridForm.php` | WP-007 |
| `tests/Cells/SelectionCellTest.php` | WP-005 |
| `phpunit.xml.dist` | WP-001 |
| `composer.json` | WP-001 |
| `docs/agents/project-manifest/api-surface.md` | WP-002, WP-004, WP-006 |
| `docs/agents/project-manifest/constraints.md` | WP-005, WP-008 |
| `docs/agents/project-manifest/data-flows.md` | WP-008 |
| `docs/agents/project-manifest/file-tree.md` | WP-008 |
