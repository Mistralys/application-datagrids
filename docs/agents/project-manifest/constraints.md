# Constraints & Conventions

## Known Bugs

No known bugs at this time. All previously tracked bugs (#1–#7) have been resolved.

---

## Project Status

**Work in progress.** The README explicitly states this. Several methods have empty or stub implementations:

| Method | File | Notes |
|---|---|---|
| `DefaultRenderer::renderCustomRow()` | `DefaultRenderer.php` | Returns empty string |
| `Bootstrap5Renderer::renderCustomRow()` | `Bootstrap5Renderer.php` | Returns empty string |

## Known Technical Debt

| Item | File | Notes |
|---|---|---|
| `InMemoryStorage` not registered under `autoload-dev` | `tests/TestClasses/InMemoryStorage.php` | `tests/bootstrap.php` now glob-loads all files in `TestClasses/`, so `InMemoryStorage` is available to all test suites without manual requires. Formal `autoload-dev` registration is deferred to WP-008. |
| `Bootstrap5Renderer::renderPaginationRow()` duplicates base-class guard logic | `src/Grids/Renderer/Types/Bootstrap5Renderer.php` | The method is a near-full copy of `BaseGridRenderer::renderPaginationRow()`, differing only in the final delegate call (`createBootstrapPaginationRow` vs `createPaginationRow`). The base class already follows the Template Method pattern — `Bootstrap5Renderer` should override `createPaginationRow()` instead to eliminate duplication. If guards are added to the base method in future WPs, this override will silently miss them. Flagged in WP-004 code review (medium priority, non-blocking). |

---

## Runtime Errors

`BaseGridRow::getGrid()` throws `DataGridException::ERROR_NO_ROW_MANAGER` (code `171702`) when called on a row instance before `setRowManager()` has been invoked. In normal usage this cannot happen because `RowManager::registerRow()` and `RowManager::getHeaderRow()` both call `setRowManager()` immediately after creating a row. It can only occur if a row is instantiated and `getGrid()` is called before the row is registered with a manager.

`StandardRow::getSelectionCell()` throws `DataGridException::ERROR_NO_VALUE_COLUMN` (code `171701`) when row selection is active (at least one action configured) but no value column has been set via `GridActions::setValueColumn()`. This is a hard error — execution halts and the developer must configure a value column before calling this method.

`DataGrid::__construct()` throws `DataGridException::ERROR_INVALID_GRID_ID` (code `260301`) when `$id` does not match the strict kebab-case pattern `/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/`. `JsonFileStorage` also throws `DataGridException::ERROR_INVALID_GRID_ID` (code `260301`) from `validateGridID()` when `$gridID` does not match `/^[a-zA-Z][a-zA-Z0-9\-_]*$/`. The `JsonFileStorage` validation is called from `getFilePath()`, the single choke point for all path construction, protecting both `get()` and `set()` against path traversal attacks (OWASP A01/A03).

---

## Autoloading

The project uses Composer **classmap** autoloading (not PSR-4):

```json
"autoload": {
    "classmap": ["src"]
}
```

This means `composer dump-autoload` must be run whenever files or classes are added/renamed/moved.

## PHP Version

Required: `php >= 8.4` (as declared in `composer.json`).

## Coding Conventions

1. **Strict types everywhere:** All files declare `declare(strict_types=1);`.
2. **Fluent interface:** All setter/configuration methods return `self` or `$this`.
3. **Interface + abstract base + concrete types:** Every major component follows the pattern `Interface → abstract Base class → concrete Types/`.
4. **Trait pairing:** Traits always have a matching interface (`AlignTrait` ↔ `AlignInterface`, `IDTrait` ↔ `IDInterface`). Classes that use a trait must implement the corresponding interface.
5. **Error codes:** Exception classes define integer constants for specific error conditions (e.g., `GridColumnException::INVALID_COLUMN_NAME = 171602`).
6. **Column name validation:** Column names must start with a letter and contain only alphanumeric characters, hyphens, and underscores (regex: `/^[a-z][a-z0-9\-_]*$/i`).
7. **Static factory:** `DataGrid::create()` provides a static factory as an alternative to `new DataGrid()`.
8. **HTMLTag usage:** All HTML output is generated through the `HTMLTag` class from `application-utils-core`, not through string concatenation.
9. **Lazy initialization:** Some components are created on first access (e.g., `GridActions` via `DataGrid::actions()`, `HeaderRow` via `RowManager::getHeaderRow()`).
10. **Auto-generated IDs:** `IDTrait::requireID()` auto-generates an ID using `JSHelper::nextElementID()` when none has been set.
11. **No constructor property promotion:** PHP 8 constructor property promotion is not used. Properties are declared explicitly at class level and assigned in the constructor body (e.g. `$this->grid = $grid`). This matches the broader codebase style and avoids implicit coupling between constructor parameters and property names.
12. **Grid ID validation:** `DataGrid` requires IDs to be strict kebab-case matching `/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/`. `JsonFileStorage` applies a looser check `/^[a-zA-Z][a-zA-Z0-9\-_]*$/` (uppercase and underscores allowed) via `validateGridID()` — this secondary check protects path construction but is intentionally broader than `DataGrid`'s constraint. `DataGridException::ERROR_INVALID_GRID_ID` (code `260301`) is thrown on violation by either class.
12. **isset() for nullable property guards:** Nullable class-type property null-checks use `isset($this->foo)` instead of `$this->foo !== null`. This is idiomatic PHP and avoids verbose triple-equals null comparisons.

## Static Analysis

PHPStan is configured at **level 6** with the PHPUnit extension. Run via:

```bash
composer analyze
```

**Current status:** 0 errors at level 6. The codebase passes PHPStan level 6 cleanly (all issues identified in this project plan have been resolved).

**Level 7 analysis:** Introduces no additional errors beyond level 6 (0 new errors at level 7).

**Level 8 analysis:** 0 errors (previously 2 — both resolved: `GridForm::setHiddenVars()` null guard added, `RendererManager::getRenderer()` null path eliminated).

## Testing

PHPUnit 12 is configured. Run via:

```bash
composer test
```

Test infrastructure is in place: `tests/bootstrap.php` requires the Composer autoloader, `phpunit.xml` configures a test suite pointing to `tests/` with named suites for each subdirectory, and subdirectories `tests/Actions/`, `tests/Cells/`, `tests/Pagination/`, `tests/Rows/`, `tests/Settings/`, `tests/Sorting/`, and `tests/Storage/` exist.

**Current test coverage (112 tests):**

| Test class | Tests | Assertions | Coverage |
|---|---|---|---|
| `GridPaginationTest` | 36 | — | `getTotalPages`, `getCurrentPage` clamping, `getPageNumbers` (small/large/boundary, ellipsis), `hasPreviousPage`/`hasNextPage`, `getPageURLTemplate`; (WP-002) `setItemsPerPageOptions`, `hasItemsPerPageOptions`, `resolveItemsPerPage` (priority chain, persist, cache, invalid GET), `getItemsPerPageURLTemplate`, `getItemsPerPageURL` (page reset), `setItemsPerPageParam` |
| `ArrayPaginationTest` | 11 | 20 | `getSlicedItems` (first/middle/last/single-item), `getPageURL` (add/replace/custom param), `totalItems`, `itemsPerPage`, `currentPage` clamping |
| `GridActionsTest` | 7 | 9 | `processSubmittedActions()`: no data, empty array, missing field, unknown action, separator skipping, callback invocation, no callback set |
| `StandardRowTest` | 5 | 5 | `getSelectValue()` with/without value column, `isSelectable()` with/without actions, `DataGridException` on empty value column |
| `SelectionCellTest` | 2 | 5 | `renderContent()` checkbox markup (type/name/value), `StandardRow::getSelectionCell()` throws `DataGridException` when value column missing |
| `ColumnSortingTest` | 18 | 51 | `useNativeSorting`/`useCallbackSorting`/`useManualSorting` flags (4), `getSortColumn()` (3), `getSortDir()` (3), native/callback/manual row sorting (5), `getSortURL()` direction toggling (2), merged-row position preservation (1) |
| `SortManagerTest` | 8 | 49 | `sortRows()` native ASC/DESC (2), callback ASC/DESC with negation (2), manual no-op (1), no-sort-column no-op (1), MergedRow position preservation (1), numeric native sort (1) |
| `RendererSortHeaderTest` | 5 | 16 | Non-sortable no-link (base/Bootstrap5), sortable has `<a>` with sort URL, active sort indicator (▲/▼), Bootstrap5 utility classes on `<a>` |
| `GridSettingsTest` | 6 | 7 | `getItemsPerPage()` null default, explicit default, set/get round-trip, fluent return, default override, per-gridID isolation; uses `InMemoryStorage` (no I/O) |
| `JsonFileStorageTest` | 7 | 11 | Read/write round-trip, default fallback (value and null), file creation on first write, multiple keys per grid, multiple grids isolated, directory creation; uses temp dir with `tearDown` cleanup |

`GridPaginationTest` uses an anonymous `PaginationInterface` stub — zero global state. `ArrayPaginationTest` uses `setUp`/`tearDown` to save/restore `$_SERVER['REQUEST_URI']`. `GridPaginationTest` IPP tests manipulate `$_GET` directly (e.g., `$_GET['ipp'] = '20'`) and use `try/finally` blocks to clean up via `unset($_GET['ipp'])` — no `setUp`/`tearDown` is needed because the IPP resolution result is cached on the `GridPagination` instance, so each test creates a fresh instance. `GridActionsTest`, `StandardRowTest`, and `SelectionCellTest` do not require `set_error_handler`/`restore_error_handler`; the missing-value-column path now throws `DataGridException` and is tested with `expectException`.

> **Note:** `phpunit.xml` sets `failOnWarning="true"`. `StandardRow::getSelectionCell()` now throws `DataGridException::ERROR_NO_VALUE_COLUMN` (code `171701`) when selection is active but no value column is set — use `expectException(DataGridException::class)` in tests; `set_error_handler`/`restore_error_handler` is no longer needed for this path.

## Dependencies on `application-utils-core`

The library heavily relies on `mistralys/application-utils-core` for:

- `HTMLTag` — HTML element generation
- `ClassableTrait` / `ClassableInterface` — CSS class management
- `RenderableBufferedTrait` / `RenderableInterface` / `RenderableTrait` — output rendering
- `StringableInterface` — string-castable objects
- `BaseException` — base exception class
- `JSHelper` — element ID generation
- `NumberInfo` / `parseNumber()` — numeric value handling with units
- `ClassHelper` — type-safe casting utilities
- `t()` — translation/localization function
