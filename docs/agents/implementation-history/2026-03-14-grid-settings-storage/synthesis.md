# Project Synthesis — Grid Settings Storage

**Plan:** `2026-03-14-grid-settings-storage`  
**Date:** 2026-03-14  
**Status:** COMPLETE  
**Work Packages:** 8 / 8 COMPLETE  

---

## Executive Summary

This session delivered a complete, production-quality **per-grid persistent settings layer** for the `application-datagrids` library, integrated end-to-end into the `webcomics-builder` consumer application.

### What Was Built

| Layer | Artifact | Description |
|---|---|---|
| Storage contract | `GridStorageInterface` | `get(string $gridID, string $key, mixed $default): mixed` + `set(...)` |
| Disk backend | `JsonFileStorage` | Writes `{storagePath}/{gridID}.json`; per-request in-memory cache; LOCK_EX writes; path-traversal guard via `validateGridID()` |
| Test double | `InMemoryStorage` | `GridStorageInterface` implementation backed by an array; used in all unit tests |
| Settings API | `GridSettings` | Typed wrapper exposing `getItemsPerPage()` / `setItemsPerPage()` (fluent); lazily created from `DataGrid` |
| DataGrid wiring | `DataGrid::create(string $id, GridStorageInterface $storage)` | Constructor now requires a mandatory grid ID (strict kebab-case validated) and storage handler |
| Consumer integration | `Manager::getGridStorage()` | Lazy singleton returning `GridStorageInterface`; used by all three WebcomicsBuilder grid pages |
| Test suites | `JsonFileStorageTest` (7 tests), `GridSettingsTest` (6 tests) | New named test suites registered in `phpunit.xml` |

---

## Metrics

| Metric | Value |
|---|---|
| Tests passing (final) | **91 / 91** |
| Test assertions | **197** |
| PHPStan errors — `application-datagrids` (level 6) | **0** |
| PHPStan errors — `webcomics-builder` (level 5) | **0** |
| Security issues in final state | **0** |
| Code-review FAILs (caught & fixed) | **2** |
| WPs with rework | **2** (WP-001, WP-003) |

### Test Growth Across the Session

| Checkpoint | Tests | Assertions |
|---|---|---|
| Session start | 78 | 179 |
| After WP-002 (GridSettingsTest) | 84 | 186 |
| After WP-005/006 (JsonFileStorageTest) | 91 | 197 |
| Session end | **91** | **197** |

---

## Security & Quality Events

### WP-001 — Path Traversal (RESOLVED)

**Code review FAIL.** `JsonFileStorage::getFilePath()` concatenated `$gridID` directly into a filesystem path without sanitisation, creating an OWASP A01/A03 path traversal vulnerability.

**Fix applied:** Added `private validateGridID(string $gridID): void` that matches `$gridID` against `/^[a-zA-Z][a-zA-Z0-9\-_]*$/` and throws `DataGridException::ERROR_INVALID_GRID_ID` (260301) on failure. All read and write paths are routed through this single choke point.

### WP-003 — Manifest Drift (RESOLVED)

**Code review FAIL.** The three manifest files (`api-surface.md`, `constraints.md`) were not updated after `DataGrid`'s constructor signature changed and the new `ERROR_INVALID_GRID_ID` constant was introduced.

**Fix applied:** All three manifest documents updated; both throw sites (DataGrid and JsonFileStorage) now documented.

---

## Strategic Recommendations

### High-Priority Follow-Up

**1. Align `JsonFileStorage` validation regex with `DataGrid` kebab-case pattern** *(medium priority, flagged by Developer, QA, and Reviewer in WP-003, WP-004, WP-008)* — **RESOLVED 2026-03-14**

`DataGrid::__construct()` enforces strict lowercase kebab-case:  
`/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/`

`JsonFileStorage::validateGridID()` uses a permissive pattern:  
`/^[a-zA-Z][a-zA-Z0-9\-_]*$/` (allows uppercase, underscores)

Since `JsonFileStorage` is a public class, it can be instantiated directly with IDs that `DataGrid` would reject (e.g. `'Grid_1'`). Both patterns prevent path traversal, but the mismatch creates a silent inconsistency in the storage contract. Align to the stricter pattern.

**Fix applied:** `JsonFileStorage::validateGridID()` regex updated to `/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/` and error message aligned with `DataGrid`. All 96 tests pass.

**2. Add `validateGridID()` exception test to `JsonFileStorageTest`** *(low priority, flagged by QA in WP-004 and Reviewer in WP-006 and WP-008)* — **RESOLVED 2026-03-14**

`JsonFileStorageTest` has no test covering the `DataGridException` thrown by `validateGridID()` for invalid IDs (e.g. `'../escape'`, `'my grid'`, empty string). This is the primary path-traversal guard — it should have a regression test.

**Fix applied:** Added 5 tests to `JsonFileStorageTest`: `testInvalidGridIDPathTraversal`, `testInvalidGridIDWithSpace`, `testInvalidGridIDEmpty`, `testInvalidGridIDUppercase`, `testInvalidGridIDUnderscore`. Storage suite: 12 tests / 16 assertions. Full suite: 96 tests / 202 assertions.

### Medium-Priority Follow-Up

**3. Fix `Manager.$gridStorage` property type** *(low priority, WP-008 Reviewer)* — **RESOLVED 2026-03-14**

`Manager.php` declares `private static ?JsonFileStorage $gridStorage = null`. The public accessor already returns `GridStorageInterface` — the property type should match to avoid a two-file update if the concrete backend ever changes:
```php
// current
private static ?JsonFileStorage $gridStorage = null;
// recommended
private static ?GridStorageInterface $gridStorage = null;
```

**Fix applied:** Property type changed to `?GridStorageInterface` in `webcomics-builder/assets/classes/Manager.php`. The `JsonFileStorage` import remains (used in the constructor call on line 23).

**4. Add positive-value guard to `GridSettings::setItemsPerPage()`** *(low priority, WP-008 Reviewer)* — **RESOLVED 2026-03-14**

`setItemsPerPage(0)` or a negative value is semantically invalid. While downstream pagination silently treats `0` as `1` via `max(1, ipp)`, the error is swallowed. Throw a `DataGridException` for `$value < 1` to make the contract explicit.

**Fix applied:** Guard added to `GridSettings::setItemsPerPage()` throwing `DataGridException::ERROR_INVALID_ITEMS_PER_PAGE` (260302) for `$value < 1`. New constant added to `DataGridException`. Two regression tests added: `testSetItemsPerPageZeroThrows`, `testSetItemsPerPageNegativeThrows`.

**5. Guard `file_put_contents()` and `json_encode()` return values in `JsonFileStorage::set()`** *(low priority, flagged across multiple WPs)* — **RESOLVED 2026-03-14**

Two silent failure modes:
- `json_encode()` returns `false` for non-serializable values → `file_put_contents()` writes an empty string, corrupting the `.json` file; the in-memory cache is updated normally, hiding the loss until next request.
- `file_put_contents()` returns `false` on permission or disk-full errors → settings silently fail to persist.

**Fix applied:** Replaced raw `json_encode` + `file_put_contents` in `set()` and `file_get_contents` + `json_decode` in `readGridData()` with `JSONFile::factory()` from `AppUtils\FileHelper\JSONFile`. `putData()` throws `FileHelper_Exception` on JSON encode failure (via `JSONConverterException`) or write failure; `getData()` throws on read or decode failure. Both failure modes now surface promptly via the library's built-in exception handling.

### Low-Priority / Cosmetic

**6. Fix `tests/bootstrap.php` glob fallback** *(WP-002 Reviewer)* — **RESOLVED 2026-03-14**
```php
// current — throws TypeError if glob() returns false
foreach (glob(__DIR__ . '/TestClasses/*.php') as $file) {
// fix
foreach (glob(__DIR__ . '/TestClasses/*.php') ?: [] as $file) {
```

**Fix applied:** `?: []` fallback added to `tests/bootstrap.php`.

**7. Extract `'items_per_page'` to a private const in `GridSettings`** *(WP-002 Reviewer)* — **RESOLVED 2026-03-14**  
Prevents typo drift as more settings keys are added.

**Fix applied:** Added `private const KEY_ITEMS_PER_PAGE = 'items_per_page'` to `GridSettings`; both `getItemsPerPage()` and `setItemsPerPage()` now reference the constant.

**8. Remove namespace declaration from `examples/1-simple-grid.php` and `examples/2-bootstrap-grid.php`** *(WP-004 Reviewer)* — **RESOLVED 2026-03-14**  
Examples 3 and 4 have no namespace; examples 1 and 2 still declare `namespace AppUtils\Examples\Grids`, which is inconsistent.

**Fix applied:** `namespace AppUtils\Examples\Grids;` removed from both example files.

**9. Configure a Composer path repository in `webcomics-builder`** *(Developer, WP-007/WP-008)*  
`webcomics-builder` installs `mistralys/application-datagrids` from `dev-main` on GitHub, requiring manual vendor sync after every local change. Adding a `path` repository entry to `webcomics-builder/composer.json` would eliminate that friction.

**10. Enforce named test suite discipline** *(WP-008 Developer)*  
The redundant `All` testsuite was removed this session to fix PHPUnit 12 exit-code-1 with duplicate files. Every future test directory added to the project **must** get its own named `<testsuite>` entry in `phpunit.xml` rather than relying on directory-scanning catch-alls.

---

## Next Steps for Planner / Technical Program Manager

1. **Pagination integration** — `GridSettings::getItemsPerPage()` exists but is not yet wired into `GridPagination`. The most natural next feature is to read `getItemsPerPage()` from `DataGrid::settings()` and honour it in the grid pagination flow, replacing hard-coded per-page values.

2. **Additional settings** — The `GridSettings` class is deliberately minimal (one setting). Consider `getSortColumn()` / `setSortColumn()` to persist sort state across requests, which would make the `Sorting` subsystem fully stateful.

3. **Address the regex inconsistency** (item 1 above) before publishing a new library release.

4. **Publish a tagged release** of `mistralys/application-datagrids` once the regex and test coverage gaps are closed, then update `webcomics-builder/composer.json` to reference the tagged version instead of `dev-main`.

---

## Pipeline Health Summary

| Work Package | Implementation | QA | Code Review | Documentation |
|---|---|---|---|---|
| WP-001 — Storage infrastructure | PASS (rework) | PASS (rework) | FAIL → PASS | — |
| WP-002 — GridSettings | PASS | PASS | PASS | PASS |
| WP-003 — DataGrid mandatory ID | PASS (rework) | PASS (rework) | FAIL → PASS | — |
| WP-004 — Example updates | PASS | PASS | PASS | PASS |
| WP-005 — Test file updates | PASS | PASS | PASS | PASS |
| WP-006 — New test suites | PASS | PASS | PASS | PASS |
| WP-007 — WebcomicsBuilder integration | PASS | PASS | PASS | PASS |
| WP-008 — Verification & autoloading | PASS | PASS | PASS | PASS |

All 8 WPs completed. 0 open blockers. All acceptance criteria met.
