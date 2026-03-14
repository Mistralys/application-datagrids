# Synthesis Report — Synthesis Observations Rework

**Plan:** `2026-03-11-synthesis-rework`
**Date:** 2026-03-12
**Status:** COMPLETE

---

## Executive Summary

This plan resolved all 7 actionable items surfaced in the [2026-03-09 Rework-1 Synthesis](../2026-03-09-grid-actions-pagination-rework-1/synthesis.md). Changes addressed four categories of quality concern: **security hardening** (XSS in page-jump JS output), **convention harmonization** (null-check style, constructor promo ban, return types), **error signaling** (trigger_error → typed exception), and **example correctness** (facade method usage). A final verification WP confirmed zero regressions and reconciled stale manifest documentation.

---

## Metrics

| Metric | Value |
|---|---|
| Work Packages | 5 / 5 COMPLETE |
| Pipeline stages PASS | 20 / 20 (5 WPs × 4 stages) |
| Pipeline stages FAIL | 0 |
| PHPUnit tests | **47 / 47 PASS** |
| PHPUnit assertions | **63** |
| PHPStan level-6 errors (new) | **0** |
| PHPStan level-6 errors (pre-existing) | 14 (unchanged) |
| Security issues introduced | 0 |

---

## Work Package Summary

### WP-001 — XSS Hardening & Constructor Convention
**Files:** `BaseGridRenderer.php`, `Bootstrap5Renderer.php`, `GridPagination.php`

Replaced bare `$inputId` / `$urlTemplate` string interpolation in page-jump JavaScript with `json_encode()` output, eliminating the XSS vector in both renderers. Converted `GridPagination` constructor from property promotion to explicit property declaration, aligning it with the codebase convention enforced in `GridActions` and `SelectionCell`.

Codified as **Coding Convention #11** in `constraints.md`.

---

### WP-002 — Null-check Unification & Dependency Inversion
**Files:** `DataGrid.php`, `RowManager.php`, `StandardRow.php`

Replaced all `$this->actions !== null` / `$this->pagination !== null` checks in `DataGrid.php` with `isset()` equivalents (6 occurrences). Changed `RowManager::$grid` property type, constructor parameter, and `getGrid()` return type from the concrete `DataGrid` to `DataGridInterface`. Propagated the return-type change up through `StandardRow::getGrid()`.

Codified as **Coding Convention #12** in `constraints.md`.

---

### WP-003 — Exception-Based Error Signaling & Constant Visibility
**Files:** `DataGridException.php`, `StandardRow.php`, `GridPagination.php`, `StandardRowTest.php`, `SelectionCellTest.php`

Added `DataGridException::ERROR_NO_VALUE_COLUMN = 171701`. Replaced `trigger_error()` in `StandardRow::getSelectionCell()` with a typed `DataGridException` throw, making the guard catchable by application code and testable via `expectException()`. Changed `GridPagination::PAGE_SENTINEL` from `private const` to `public const`. Updated both affected test cases to use `expectException()` in place of `set_error_handler` / `expectWarning()`.

---

### WP-004 — Example Facade Correction
**Files:** `examples/3-grid-actions.php`

Single-line swap: replaced `$grid->actions()->processSubmittedActions()` with the preferred idempotent facade `$grid->processActions()`. The facade additionally guards against double-processing via the `actionsProcessed` flag, making the example slightly more robust than the prior direct call.

---

### WP-005 — Full Verification & Manifest Reconciliation
**Files:** `constraints.md`, `StandardRowTest.php`, `AGENTS.md`

Integration verification WP. Confirmed 47/47 tests PASS and zero new PHPStan errors after merging WP-001 through WP-004. Corrected stale per-class test/assertion counts in `constraints.md` (GridPaginationTest: 20/34 → 22/24; ArrayPaginationTest: 13/10 → 11/20; StandardRowTest: 5/7 → 5/5; SelectionCellTest: 2/9 → 2/5). Removed stale `E_USER_WARNING` comment from `StandardRowTest.php`. Corrected stale test distribution breakdown in `AGENTS.md` (20+13 → 22+11 for pagination subtests).

---

## Source Files Modified

| File | Change |
|---|---|
| `src/Grids/Renderer/BaseGridRenderer.php` | json_encode() XSS hardening |
| `src/Grids/Renderer/Types/Bootstrap5Renderer.php` | json_encode() XSS hardening |
| `src/Grids/Pagination/GridPagination.php` | Constructor convention + PAGE_SENTINEL visibility |
| `src/Grids/DataGrid.php` | isset() null-check unification (6 occurrences) |
| `src/Grids/Rows/RowManager.php` | DataGridInterface property/return types |
| `src/Grids/Rows/Types/StandardRow.php` | DataGridInterface return type, DataGridException throw |
| `src/Grids/DataGridException.php` | ERROR_NO_VALUE_COLUMN constant |
| `tests/Rows/StandardRowTest.php` | expectException(), stale comment fix |
| `tests/Cells/SelectionCellTest.php` | expectException() |
| `examples/3-grid-actions.php` | processActions() facade |
| `docs/agents/project-manifest/api-surface.md` | PAGE_SENTINEL public, getGrid() types, ERROR_NO_VALUE_COLUMN |
| `docs/agents/project-manifest/constraints.md` | Convention #11 & #12, exception docs, test counts |
| `AGENTS.md` | Per-class test distribution corrected |

---

## Outstanding Technical Debt

These issues were observed during the plan but are out of scope. They represent the **recommended inbox** for the next planning cycle.

### High-Priority Debt (14 pre-existing PHPStan errors)

**`HeaderRow.php` and `MergedRow.php` missing interface implementations** *(medium, flagged by Developer + Reviewer in WP-005)*
Both classes extend `BaseGridRow` but do not implement `getGrid()` or `isSelectable()` from `GridRowInterface`. This is the primary source of the 14 pre-existing PHPStan level-6 errors. Resolving these would eliminate the majority of the PHPStan baseline.

### Medium-Priority Debt

**`RowManager` type annotations** *(medium, flagged by Developer in WP-005)*
`addArrays()`, `addArray()`, and `addMerged()` lack `array<string,mixed>` type annotations; `addMerged()` `$content` parameter has no type at all. Addressing these would clear 3 of the 14 pre-existing PHPStan errors.

**`constraints.md` test-count table** *(resolved inline by Reviewer in WP-005 — confirmed accurate)*

### Low-Priority Debt

**`json_encode()` robustness** *(low, flagged by QA and Reviewer in WP-001)*
Both page-jump renderers call `json_encode($inputId)` and `json_encode($urlTemplate)` without `JSON_THROW_ON_ERROR`. If either argument contained malformed UTF-8, `json_encode` would return `false`, silently producing a broken (but non-exploitable) button. Adding `JSON_THROW_ON_ERROR` makes failure explicit.

**Dead code in `SelectionCell::renderContent()`** *(low, flagged by Developer in WP-003)*
The empty-value branch in `renderContent()` is now dead code — the guard was moved upstream to `getSelectionCell()`. The dead branch could be removed to simplify `SelectionCell`.

**Test rename: `test_renderContent_emptyValue`** *(low, flagged by Reviewer in WP-003)*
`SelectionCellTest::test_renderContent_emptyValue` no longer tests `renderContent()` — it now verifies the exception thrown by `getSelectionCell()`. Rename to `test_getSelectionCell_throwsOnMissingValueColumn` for accuracy.

---

## Strategic Recommendations

1. **Upgrade PHPStan to level 7 or 8 after clearing the baseline.** Once `HeaderRow`, `MergedRow`, and the `RowManager` annotation gaps are fixed, the baseline reaches 0 errors at level 6. Incrementally raising the level will surface the `json_encode() → string|false` issue automatically (flagged at level 8+).

2. **Extract shared page-jump logic into a base renderer template method.** `BaseGridRenderer::createPageJumpInput()` and `Bootstrap5Renderer::createBootstrapPageJumpInput()` are near-identical except for CSS classes. A template-method in the base class with an overridable container hook would eliminate the drift risk: future security fixes would only need applying once.

3. **Continue the interface-driven dependency inversion.** The `RowManager` → `DataGridInterface` change (WP-002) is a pattern worth propagating. Audit any remaining internal components that accept or hold a concrete `DataGrid` reference rather than `DataGridInterface`.

4. **Formalize the exception catalogue in `DataGridException`.** The addition of `ERROR_NO_VALUE_COLUMN` sets a good precedent. Audit remaining `trigger_error()` call sites (if any) and any uncoded developer-facing errors, and convert them to typed exceptions with documented codes. This makes the public error surface inspectable and testable.

---

## Next Steps for Planner

1. Open a new plan targeting the **14 pre-existing PHPStan errors**: implement `getGrid()` and `isSelectable()` in `HeaderRow` and `MergedRow`; add type annotations to `RowManager::addArrays()`, `addArray()`, `addMerged()`.
2. Add `JSON_THROW_ON_ERROR` to the `json_encode()` calls in both page-jump renderers.
3. Extract the shared page-jump HTML/JS builder into a base-class template method.
4. Remove dead empty-value branch from `SelectionCell::renderContent()` and rename the stale test method.
5. Evaluate PHPStan level upgrade once the baseline is clear.
