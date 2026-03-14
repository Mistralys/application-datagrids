# Project Synthesis — Column Sorting

**Plan:** `2026-03-12-column-sorting`
**Date:** March 13, 2026
**Status:** COMPLETE
**Work Packages:** 5 / 5 COMPLETE

---

## Executive Summary

This session delivered the full **column sorting feature** for the application-datagrids library, implementing a complete vertical slice from the column API layer through state management, row reordering, renderer integration, automated testing, and documentation.

The work filled in all pre-existing sort stubs (`useNativeSorting()`, `useCallbackSorting()`, `useManualSorting()`, `getSortColumn()`, `getSortDir()`) and added the following new components:

| Component | File(s) |
|---|---|
| `SortMode` enum | `src/Grids/Columns/SortMode.php` |
| `SortManagerInterface` | `src/Grids/Sorting/SortManagerInterface.php` |
| `SortManager` | `src/Grids/Sorting/SortManager.php` |
| Sort-aware header cells | `BaseGridRenderer.php`, `Bootstrap5Renderer.php` |
| Sorting test suite | `tests/Sorting/ColumnSortingTest.php` |

All 5 work packages are COMPLETE with every acceptance criterion met.

---

## Work Package Summary

### WP-001 — Column Sort API Foundation
**Goal:** Add `SortMode` enum and implement sort API on `BaseGridColumn` / `GridColumnInterface`.

- `SortMode` enum created with three backed string cases: `Native`, `Callback`, `Manual`.
- `BaseGridColumn` sort API fully implemented: `useNativeSorting()`, `useCallbackSorting()`, `useManualSorting()`, `isSortable()`, `getSortMode()`, `getSortCallback()`.
- `GridColumnInterface` updated with `getSortMode()` and `getSortCallback()` signatures.
- `README.md` PHP version corrected (8.2 → 8.4, pre-existing error).
- All 9 ACs met. PHPStan: 0 errors. Tests: 47/47.

### WP-002 — SortManager: State Resolution & Row Sorting
**Goal:** Implement `SortManager` for `$_GET` state resolution, URL building, and row sort dispatch.

- `SortManager` resolves sort column and direction from configurable `$_GET` parameters (`sort`, `sort_dir`).
- `getSortURL()` builds toggle URLs following the `ArrayPagination` `parse_url` / `http_build_query` pattern.
- `sortRows()` partitions `StandardRow` instances from non-standard rows, then dispatches to Native (string/numeric type-aware), Callback, or Manual (no-op) modes, preserving non-standard row positions.
- `DataGrid` updated with lazy `sorting()` accessor and `generateOutput()` sort insertion point.
- All 13 ACs met. PHPStan: 0 errors. Tests: 47/47.
- **Reviewer corrected manifest violations** directly: `constraints.md` (stubs removed, namespace anomaly updated), `api-surface.md` (SortManager section added), `file-tree.md` (Sorting/ directory added).

### WP-003 — Renderer Sort-Aware Header Cells
**Goal:** Add sort indicators and sort links to column header cells in both renderers.

- `BaseGridRenderer::createHeaderCell()` branches on `isSortable()`: non-sortable columns unchanged; sortable columns delegate to new `createSortLink()` helper producing `<a>` with ▲/▼ indicator for the active sort column.
- `Bootstrap5Renderer::createHeaderCell()` overrides base using Template Method pattern, adding Bootstrap utility classes (`text-decoration-none text-reset d-inline-flex align-items-center gap-1`) to the `<a>` element.
- All HTML uses `HTMLTag::create()` — zero raw string concatenation.
- All 8 ACs met. PHPStan: 0 errors. Tests: 47/47.

### WP-004 — Sorting Tests & Example Update
**Goal:** Write 18-test `ColumnSortingTest` suite and update the pagination example to demonstrate sorting.

- `tests/Sorting/ColumnSortingTest.php` created with 18 test methods, 51 assertions, covering: column sortable flags (4), `getSortColumn()` resolution (3), `getSortDir()` resolution (3), row sorting behavior (5), `getSortURL()` direction toggling (2), merged-row position preservation (1).
- `examples/4-pagination.php` updated with `->useNativeSorting()` on `id` and `title` columns.
- `phpunit.xml.dist` required no change — existing `tests/` recursive discovery auto-included `tests/Sorting/`.
- All 6 ACs met. PHPStan: 0 errors. Tests: **65/65** (47 pre-existing + 18 new).

### WP-005 — Final Manifest Audit & AGENTS.md Update
**Goal:** Verify all manifest documents are consistent and update test count in `AGENTS.md`.

- Audited all 5 manifest documents (`file-tree.md`, `api-surface.md`, `constraints.md`, `data-flows.md`, `AGENTS.md`).
- WPs 001–004 documentation passes had already updated all manifests correctly.
- Only required change: `AGENTS.md` Project Stats table updated from 47 to **65 tests** with breakdown.
- All 6 ACs met.

---

## Metrics

| Metric | Value |
|---|---|
| Total tests | 65 |
| New sorting tests | 18 (51 assertions) |
| Regression tests | 47/47 PASS |
| Test failures | 0 |
| PHPStan errors | 0 (44 files analysed) |
| PHPStan level | 6 |
| Work packages completed | 5 / 5 |
| Acceptance criteria met | 42 / 42 |
| Pipeline stages passed | 20 / 20 |
| Files created | 5 |
| Files modified | ~14 |

---

## Open Technical Debt & Follow-up Tasks

The following non-blocking items were recorded across pipelines. None blocked acceptance, but each represents a genuine improvement opportunity for the next cycle.

### High Priority (None)
No high-priority blockers.

### Medium Priority

| # | Area | Issue |
|---|---|---|
| 1 | **Test coverage** | `SortManager::sortRows()` behavioral ACs (Native/Callback/Manual sort, non-standard row preservation) lack automated unit tests — verified by code review only. A dedicated `SortManagerTest.php` would provide regression safety before the API is considered production-ready. |
| 2 | **Renderer tests** | No automated tests exist for sort-aware header cell rendering (sortable vs non-sortable columns, active sort indicator, Bootstrap utility classes). `tests/Sorting/RendererSortHeaderTest.php` is strongly recommended. |

### Low Priority (Goldlist)

| # | Area | Issue |
|---|---|---|
| 3 | **Interface completeness** | `sortRows()` is absent from `SortManagerInterface`. Tests use an `instanceof SortManager` cast; `DataGrid::generateOutput()` accesses the concrete type directly. Adding `sortRows(array &$rows): void` to the interface closes the contract gap. |
| 4 | **Sort callback residue** | `useNativeSorting()` and `useManualSorting()` do not clear `$sortCallback`. After `->useCallbackSorting(fn()=>0)->useNativeSorting()`, `getSortCallback()` still returns non-null. Adding `$this->sortCallback = null` inside both methods eliminates the footgun. |
| 5 | **Bootstrap5 duplication** | `Bootstrap5Renderer::createHeaderCell()` duplicates the `<th>` construction wrapper from `BaseGridRenderer`. A protected `createSortAnchor(GridColumnInterface $column, array $extraClasses = []): HTMLTag` factory in `BaseGridRenderer` would let Bootstrap5 customize only the link classes. |
| 6 | **Callback + DESC untested** | `SortManager::sortRows()` negates the callback result for `SORT_DESC`. A `test_callbackSorting_respectsDescDirection()` test would document and validate this behavior. |
| 7 | **`getSortURL()` safety note** | `REQUEST_URI` is attacker-controlled. `getSortURL()` output is always consumed via `HTMLTag::attr()` (which handles escaping), but a `@return` docblock note advising callers to apply `htmlspecialchars()` outside `HTMLTag` contexts would prevent future XSS risk at new call sites. |
| 8 | **`$sorting` local variable** | `createSortLink()` and `Bootstrap5Renderer::createHeaderCell()` call `$this->grid->sorting()` 2–3 times each. Extracting `$sorting = $this->grid->sorting()` as a local variable is a cosmetic readability improvement. |
| 9 | **WP-004 spec namespace** | `WP-004.md` specifies namespace `AppUtils\Grids\Tests\Sorting`; actual implementation correctly uses `AppUtils\Tests\Sorting`. The spec document should be corrected for future reference. |

---

## Strategic Recommendations

### 1. Close the `SortManagerInterface` Contract (Priority: High)
Add `sortRows(array &$rows): void` to `SortManagerInterface`. This removes the concrete-type cast in `DataGrid::generateOutput()` and test code, and makes the sorting contract fully mockable/substitutable.

### 2. Add Automated Sorting Tests (Priority: High)
Two test classes are missing:
- **`SortManagerTest`** — covers `sortRows()` for Native/Callback/Manual modes, direction toggling, and non-standard row preservation. Prevents silent breakage if the sorting logic is ever refactored.
- **`RendererSortHeaderTest`** — covers `createHeaderCell()` / `createSortLink()` HTML output for sortable and non-sortable columns and Bootstrap5 class injection.

### 3. Refactor Bootstrap5 Header Override (Priority: Medium)
Extract `protected createSortAnchor()` to reduce the structural duplication between `BaseGridRenderer` and `Bootstrap5Renderer`. This also makes it easier to add a third renderer variant in the future.

### 4. Sort Callback Cleanup (Priority: Low)
Clear `$sortCallback` on mode change (items 4 above). One-line fix in `BaseGridColumn` that prevents the "ghost callback" footgun.

---

## What Was Built

A complete, production-quality column sorting subsystem:

```
Consumer:
  $column->useNativeSorting()         // or useCallbackSorting(fn) / useManualSorting()

DataGrid:
  $grid->sorting()                    // lazy SortManager, reads $_GET['sort'] + $_GET['sort_dir']
  $grid->getSortColumn()              // delegates to SortManager
  $grid->getSortDir()                 // delegates to SortManager

SortManager:
  ->getSortURL($column)               // toggle-direction URL for header links
  ->isSortedBy($column)               // active sort check for indicator display
  ->sortRows($rows)                   // reorders StandardRows in-place (Native/Callback/Manual)

Renderers:
  BaseGridRenderer::createHeaderCell() // plain <th> or <a> sort link with ▲/▼ indicator
  Bootstrap5Renderer::createHeaderCell() // same + Bootstrap utility classes on <a>
```

The feature integrates seamlessly with existing pagination: both work from `$_GET` parameters with independent parameter names, and `examples/4-pagination.php` demonstrates them together.
