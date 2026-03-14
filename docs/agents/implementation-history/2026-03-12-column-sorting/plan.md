# Plan — Column Sorting

## Summary

Implement the column sorting feature for the data grid library. This fills in the existing stub methods (`useNativeSorting()`, `useCallbackSorting()`, `useManualSorting()`, `getSortColumn()`, `getSortDir()`) and adds the full vertical slice: URL state management, row reordering (native & callback modes), header cell sort indicators with clickable links, and renderer support for both `DefaultRenderer` and `Bootstrap5Renderer`. Manual sorting delegates entirely to the consumer and only provides state introspection.

## Architectural Context

### Existing stubs (all return `$this` or safe defaults today)

| Method | File | Current behaviour |
|---|---|---|
| `BaseGridColumn::useNativeSorting()` | `src/Grids/Columns/BaseGridColumn.php` | Returns `$this`; does **not** set `$sortable = true` |
| `BaseGridColumn::useCallbackSorting(callable)` | `src/Grids/Columns/BaseGridColumn.php` | Returns `$this`; `// TODO` |
| `BaseGridColumn::useManualSorting()` | `src/Grids/Columns/BaseGridColumn.php` | Returns `$this`; `// TODO` |
| `BaseGridColumn::isSortable()` | `src/Grids/Columns/BaseGridColumn.php` | Reads `$sortable` (always `false`) |
| `DataGrid::getSortColumn()` | `src/Grids/DataGrid.php` | Returns `null` |
| `DataGrid::getSortDir()` | `src/Grids/DataGrid.php` | Returns `DataGridInterface::SORT_ASC` |

### Relevant patterns to follow

- **URL state** — `ArrayPagination` reads `$_GET[$pageParam]` in its constructor and builds URLs via `parse_url` / `http_build_query`. Sorting should follow the same pattern with its own query parameter names.
- **Renderer header cells** — `BaseGridRenderer::createHeaderCell()` builds a `<th>` with the column label. This is the hook point for sort links and direction indicators.
- **Template Method** — `Bootstrap5Renderer` overrides base renderer methods (e.g. `createPageJumpContainer`). The same pattern applies to sort header cells.
- **Fluent API** — all setters return `self`/`$this`.
- **Interface-driven** — public API declared on interfaces, implemented in base classes.
- **HTMLTag** — all HTML built via `HTMLTag::create()`.

### Integration points

- `DataGrid::generateOutput()` — currently calls `resolveRows()` but performs no sort step. The sorting pass must run **between** `resolveRows()` and `renderBody()`.
- `BaseGridRenderer::renderHeaderCell()` / `createHeaderCell()` — must become sort-aware for clickable column headers.
- `GridColumnInterface` — already declares the three `use*Sorting()` methods and `isSortable()`.
- `DataGridInterface` — already declares `getSortColumn()`, `getSortDir()`, and the `SORT_ASC` / `SORT_DESC` constants.

## Approach / Architecture

### Sorting modes (enum-like)

Introduce a `SortMode` enum (`src/Grids/Columns/SortMode.php`) with three cases: `Native`, `Callback`, `Manual`. Store the active mode + optional callback on `BaseGridColumn`.

### State flow

1. **Configuration time** — Consumer calls `$column->useNativeSorting()` (or callback / manual).
2. **Request resolution** — `DataGrid` reads `$_GET` (sort column name + direction) and resolves the active sort column + direction. Two new query parameter names are configurable on a new `SortManager` (default: `sort` for column name, `sort_dir` for direction).
3. **Row sorting** — In `DataGrid::generateOutput()`, after `resolveRows()`, `SortManager::sortRows()` reorders `StandardRow` instances in-place:
   - **Native**: compare `StandardRow::getCell($column)->getValue()` using PHP's spaceship operator, type-aware (string `strcasecmp`, numeric `<=>`, mixed `strval` fallback).
   - **Callback**: invoke the stored callable with `($rowA, $rowB, $column)`.
   - **Manual**: no-op — the consumer is responsible for pre-sorting.
4. **Rendering** — `renderHeaderCell()` wraps the label in an `<a>` tag linking to the toggled sort direction when the column is sortable. An indicator (▲ / ▼ or Bootstrap icon class) is appended to the currently sorted column.

### New files

| File | Type | Purpose |
|---|---|---|
| `src/Grids/Columns/SortMode.php` | Enum | `Native`, `Callback`, `Manual` |
| `src/Grids/Sorting/SortManager.php` | Class | Sort state resolution from `$_GET`, URL building, row sorting dispatch |
| `src/Grids/Sorting/SortManagerInterface.php` | Interface | Public API for `SortManager` |
| `tests/Sorting/ColumnSortingTest.php` | Test | Unit tests for sorting |

### Modified files

| File | Changes |
|---|---|
| `src/Grids/Columns/BaseGridColumn.php` | Store `SortMode`, callback; `useNativeSorting()` / `useCallbackSorting()` / `useManualSorting()` set `$sortable = true` + mode; new getter `getSortMode()`, `getSortCallback()` |
| `src/Grids/Columns/GridColumnInterface.php` | Add `getSortMode(): ?SortMode`, `getSortCallback(): ?callable` |
| `src/Grids/DataGrid.php` | Add `SortManager` lazy property; implement `getSortColumn()` and `getSortDir()` by delegating to `SortManager`; insert sort step in `generateOutput()` |
| `src/Grids/DataGridInterface.php` | Add `sorting(): SortManagerInterface` accessor |
| `src/Grids/Renderer/BaseGridRenderer.php` | `createHeaderCell()` — wrap label in `<a>` when column is sortable; add direction indicator for the active sort column |
| `src/Grids/Renderer/GridRendererInterface.php` | No change needed — `renderHeaderCell()` signature is unchanged |
| `src/Grids/Renderer/Types/Bootstrap5Renderer.php` | Override `createHeaderCell()` to produce Bootstrap-styled sort links (e.g. `text-decoration-none`, `d-inline-flex align-items-center gap-1`) |
| `examples/4-pagination.php` | Add sorting to one or two columns to demonstrate the feature |

## Rationale

- **SortManager as a separate class** — follows the Manager pattern already used by `ColumnManager`, `RowManager`, `RendererManager`. Keeps `DataGrid` lean. Encapsulates URL parameter handling (same responsibility boundary as `GridPagination`).
- **SortMode enum** — PHP 8.4 native enum avoids magic strings and allows exhaustive matching. Aligns with the project's strict-types, modern-PHP style.
- **`$_GET`-based state** — matches the pagination precedent (`ArrayPagination` reads `$_GET`). Sort state must survive page navigation (pagination links), so query parameters are the natural transport.
- **Native sort by cell value** — `StandardRow::getCell()->getValue()` already exists. Using the column's `formatValue()` would sort formatted output (wrong for numbers); raw values are correct.
- **Sorting runs inside `generateOutput()`** — this is the simplest insertion point. Sorting before render means the consumer can still mutate rows up until `echo $grid`. For `Manual` mode, the consumer pre-sorts before calling render (no library intervention needed).
- **No JavaScript** — sorting is server-side via full page reload, matching the library's existing approach (pagination is also server-side).

## Detailed Steps

### Step 1 — Create `SortMode` enum

Create `src/Grids/Columns/SortMode.php`:
```php
enum SortMode: string {
    case Native = 'native';
    case Callback = 'callback';
    case Manual = 'manual';
}
```

### Step 2 — Update `BaseGridColumn` sort methods

In `src/Grids/Columns/BaseGridColumn.php`:

- Add properties: `private ?SortMode $sortMode = null;` and `private ?callable $sortCallback = null;` (remove the existing `$sortable` since `$sortMode !== null` serves as the sortable flag).
- `useNativeSorting()`: set `$sortMode = SortMode::Native`, return `$this`.
- `useCallbackSorting(callable $callback)`: set `$sortMode = SortMode::Callback`, store `$callback`, return `$this`. Change return type from `GridColumnInterface` to `self` (fluent API precision — the interface declares `self` already).
- `useManualSorting()`: set `$sortMode = SortMode::Manual`, return `$this`. Change return type from `GridColumnInterface` to `self`.
- `isSortable()`: return `$this->sortMode !== null`.
- Add `getSortMode(): ?SortMode` — returns the mode or `null` if not sortable.
- Add `getSortCallback(): ?callable` — returns the callback (only meaningful for `Callback` mode).

### Step 3 — Update `GridColumnInterface`

Add to `src/Grids/Columns/GridColumnInterface.php`:
```php
public function getSortMode(): ?SortMode;
public function getSortCallback(): ?callable;
```

### Step 4 — Create `SortManagerInterface` and `SortManager`

Create `src/Grids/Sorting/SortManagerInterface.php`:
```php
interface SortManagerInterface {
    public function getSortColumn(): ?GridColumnInterface;
    public function getSortDir(): string;
    public function getSortURL(GridColumnInterface $column): string;
    public function isSortedBy(GridColumnInterface $column): bool;
    public function setColumnParam(string $param): self;
    public function setDirectionParam(string $param): self;
    public function getColumnParam(): string;
    public function getDirectionParam(): string;
}
```

Create `src/Grids/Sorting/SortManager.php`:
- Constructor: `__construct(DataGridInterface $grid)`.
- Properties: `$columnParam = 'sort'`, `$dirParam = 'sort_dir'`.
- `resolveSortState()` — called lazily on first access. Reads `$_GET[$columnParam]` and `$_GET[$dirParam]`. Validates: the column name must exist in `ColumnManager` and `isSortable()` must be true. If invalid, both resolve to `null` / `SORT_ASC`.
- `getSortColumn()` / `getSortDir()` — return the resolved values.
- `getSortURL(GridColumnInterface $column)` — builds a URL by rewriting the current `$_SERVER['REQUEST_URI']` query string (same pattern as `ArrayPagination::getPageURL()`). If the column is the currently sorted column, toggle the direction; otherwise default to `SORT_ASC`.
- `isSortedBy(GridColumnInterface $column)` — convenience for renderers.
- `sortRows(array &$rows)` — accepts the `GridRowInterface[]` from `resolveRows()`. Iterates once: partitions into `StandardRow[]` and non-standard rows (preserving insertion order). Sorts the `StandardRow` partition using `usort()`:
  - **Native**: `$a->getCell($col)->getValue() <=> $b->getCell($col)->getValue()` with `strcasecmp` for strings, spaceship for numerics, and `strval` fallback. Reverse for `DESC`.
  - **Callback**: `call_user_func($column->getSortCallback(), $a, $b, $column)`. Reverse the sign for `DESC`.
  - **Manual**: no-op.
  Then reassemble the full row array with sorted `StandardRow`s in their original relative positions (non-standard rows like `MergedRow` remain in place).

### Step 5 — Integrate `SortManager` into `DataGrid`

In `src/Grids/DataGrid.php`:
- Add lazy property `private ?SortManager $sortManager = null;`.
- Add method `public function sorting(): SortManagerInterface` — lazy-creates and returns the `SortManager`.
- Rewrite `getSortColumn()` → delegate to `$this->sorting()->getSortColumn()`.
- Rewrite `getSortDir()` → delegate to `$this->sorting()->getSortDir()`.
- In `generateOutput()`, after `$rows = $this->resolveRows();`, add:
  ```php
  if (isset($this->sortManager)) {
      $this->sortManager->sortRows($rows);
  }
  ```
  This ensures sorting only runs when the consumer has accessed `sorting()` (opt-in via the lazy pattern, consistent with actions/pagination).

### Step 6 — Update `DataGridInterface`

Add to `src/Grids/DataGridInterface.php`:
```php
public function sorting(): SortManagerInterface;
```

### Step 7 — Renderer header cell sort links

In `src/Grids/Renderer/BaseGridRenderer.php` — modify `createHeaderCell()`:
- If `$column->isSortable()`:
  - Wrap the label text in an `<a>` element with `href` pointing to `$this->grid->sorting()->getSortURL($column)`.
  - If the column is the current sort column (`$this->grid->sorting()->isSortedBy($column)`), append a direction indicator: `▲` for ASC, `▼` for DESC.
- If not sortable: keep current rendering (plain text label).

In `src/Grids/Renderer/Types/Bootstrap5Renderer.php` — override `createHeaderCell()`:
- Same logic, but with Bootstrap classes: `text-decoration-none text-reset` on the `<a>`, a `<span>` indicator with a small margin, and `cursor: pointer` styling.

### Step 8 — Write tests (`tests/Sorting/ColumnSortingTest.php`)

| Test | Description |
|---|---|
| `test_useNativeSorting_marksSortable` | After `useNativeSorting()`, `isSortable()` returns true, `getSortMode()` returns `SortMode::Native` |
| `test_useCallbackSorting_marksSortable` | After `useCallbackSorting()`, `isSortable()` returns true, mode is `Callback`, callback is stored |
| `test_useManualSorting_marksSortable` | After `useManualSorting()`, `isSortable()` returns true, mode is `Manual` |
| `test_column_notSortableByDefault` | `isSortable()` defaults false, `getSortMode()` returns null |
| `test_getSortColumn_returnsNull_whenNoRequest` | `SortManager::getSortColumn()` → null when `$_GET` is empty |
| `test_getSortColumn_resolvesFromGet` | Set `$_GET['sort']` to a valid sortable column name → `getSortColumn()` returns that column |
| `test_getSortColumn_ignoresNonSortableColumn` | Set `$_GET['sort']` to a non-sortable column name → `getSortColumn()` returns null |
| `test_getSortDir_defaultsToAsc` | When no `$_GET['sort_dir']`, `getSortDir()` returns `SORT_ASC` |
| `test_getSortDir_parsesDESC` | `$_GET['sort_dir'] = 'DESC'` → returns `SORT_DESC` |
| `test_getSortDir_ignoresInvalidValues` | `$_GET['sort_dir'] = 'INVALID'` → returns `SORT_ASC` |
| `test_nativeSorting_sortsStringAsc` | Grid with native-sorted string column → rows sorted A→Z |
| `test_nativeSorting_sortsStringDesc` | Same, DESC → rows sorted Z→A |
| `test_nativeSorting_sortsNumericAsc` | Grid with native-sorted numeric column → rows sorted 1→N |
| `test_callbackSorting_usesCallback` | Custom callback reverses the default order → verify sorting |
| `test_manualSorting_doesNotReorder` | Manual mode → rows remain in insertion order |
| `test_getSortURL_togglesDirection` | When sorted ASC by column X, `getSortURL(X)` produces a URL with `sort_dir=DESC` |
| `test_getSortURL_defaultsAsc_forOtherColumn` | When sorted by column X, `getSortURL(Y)` produces a URL with column Y and `sort_dir=ASC` |
| `test_sortingPreservesMergedRows` | A grid with mixed `StandardRow` / `MergedRow` → merged rows stay in original positions |

Tests will use `setUp` / `tearDown` to save and restore `$_GET` and `$_SERVER['REQUEST_URI']` (same pattern as `ArrayPaginationTest`).

### Step 9 — Add sorting example

Update `examples/4-pagination.php` (or create `examples/5-column-sorting.php` if preferred — creating a new example file is cleaner so the pagination example stays focused):

Create `examples/5-column-sorting.php`:
- Same dataset as `4-pagination.php` (200 items with id, title, category, status).
- Enable native sorting on `id` and `title` columns.
- Enable callback sorting on `category` (custom alphabetical order).
- Enable manual sorting on `status` (to demonstrate the API, with a note that the consumer would pre-sort).
- Combine with pagination to demonstrate sort state surviving page navigation.

### Step 10 — Run PHPStan & tests, update manifest

- Run `composer analyze` — fix any level 6 errors.
- Run `composer test` — all tests green.
- Run `composer dump-autoload` — new files need classmap update.
- Update project manifest documents (see Dependencies section).

## Dependencies

Steps are mostly sequential:

- Step 1 (enum) is independent.
- Step 2 depends on Step 1.
- Step 3 depends on Step 2.
- Step 4 depends on Steps 1–3.
- Step 5 depends on Step 4.
- Step 6 depends on Step 4.
- Step 7 depends on Steps 5–6.
- Step 8 depends on Steps 1–7 (needs all classes to exist).
- Step 9 depends on Steps 1–7.
- Step 10 runs last.

## Required Components

### New files
- `src/Grids/Columns/SortMode.php` — Enum
- `src/Grids/Sorting/SortManager.php` — Class
- `src/Grids/Sorting/SortManagerInterface.php` — Interface
- `tests/Sorting/ColumnSortingTest.php` — Test class
- `examples/5-column-sorting.php` — Example

### Modified files
- `src/Grids/Columns/BaseGridColumn.php`
- `src/Grids/Columns/GridColumnInterface.php`
- `src/Grids/DataGrid.php`
- `src/Grids/DataGridInterface.php`
- `src/Grids/Renderer/BaseGridRenderer.php`
- `src/Grids/Renderer/Types/Bootstrap5Renderer.php`
- `docs/agents/project-manifest/api-surface.md`
- `docs/agents/project-manifest/file-tree.md`
- `docs/agents/project-manifest/data-flows.md`
- `docs/agents/project-manifest/constraints.md`

## Assumptions

- Sorting is **server-side** via full page reloads (no JavaScript client-side sorting). This matches the existing pagination approach.
- Only one column can be sorted at a time (single-column sort). Multi-column sorting is out of scope.
- The sort query parameters (`sort`, `sort_dir`) must coexist with pagination parameters (`page`). When the user changes the sort, the page resets to 1 (pagination links must include the current sort params, and sort links must drop the page param or reset it to 1).
- `MergedRow` instances are **not** sortable — they remain in their original insertion positions. Only `StandardRow` instances participate in sorting.
- Manual sorting provides **state only** (which column + direction was requested) — the sort itself is the consumer's responsibility. The library does not reorder rows in manual mode.
- The callback for `useCallbackSorting()` receives `(StandardRow $a, StandardRow $b, GridColumnInterface $column): int` following the `usort` comparator convention.

## Constraints

- `declare(strict_types=1)` in all new files.
- Fluent API: all setters return `self`/`$this`.
- HTML via `HTMLTag` — no raw string concatenation.
- `classmap` autoloading — `composer dump-autoload` required after adding new files.
- PHPStan level 6 must pass with 0 errors.
- All existing 47 tests must continue passing.

## Out of Scope

- Client-side (JavaScript) sorting.
- Multi-column sorting.
- Persisting sort state to session or cookies.
- Sort indicators via icon fonts or SVG (plain Unicode arrows ▲/▼ are sufficient; Bootstrap renderer may enhance later).
- `DefaultRenderer` and `Bootstrap5Renderer` sort-specific CSS styling beyond basic link styling.
- Implementing `renderCustomRow()` stubs.

## Acceptance Criteria

1. `$column->useNativeSorting()` sets the column as sortable and `isSortable()` returns `true`.
2. `$column->useCallbackSorting(fn)` stores the callback and sets the column as sortable.
3. `$column->useManualSorting()` sets the column as sortable without automatic row reordering.
4. `$grid->sorting()` returns a `SortManagerInterface` instance.
5. `$grid->getSortColumn()` returns the resolved currently-sorted column (or `null`).
6. `$grid->getSortDir()` returns `'ASC'` or `'DESC'`.
7. When a sortable column is configured and `$_GET` contains valid sort params, rows are reordered in the rendered output (native and callback modes).
8. Manual mode does **not** reorder rows — `getSortColumn()` and `getSortDir()` still return the requested values for the consumer to use.
9. Header cells for sortable columns render as clickable links that toggle sort direction.
10. The currently sorted column's header shows a direction indicator (▲ or ▼).
11. Non-sortable columns render as plain text headers (unchanged).
12. Sort parameters are preserved in pagination links and vice versa.
13. `MergedRow` instances are not displaced by sorting.
14. PHPStan level 6 passes with 0 errors.
15. All new tests pass. All existing 47 tests continue to pass.
16. `examples/5-column-sorting.php` demonstrates native, callback, and manual sorting with pagination.

## Testing Strategy

- **Unit tests** in `tests/Sorting/ColumnSortingTest.php` covering:
  - Column sort configuration (all three modes + default).
  - `SortManager` state resolution from `$_GET` (valid column, invalid column, missing params, invalid direction).
  - Native sorting (string ASC/DESC, numeric ASC/DESC).
  - Callback sorting (custom comparator).
  - Manual sorting (no-op verification).
  - URL generation (direction toggling, default for other columns).
  - Mixed row type preservation (`MergedRow` stays in place).
- **Integration**: run `composer test` to confirm no regressions in the existing 47 tests.
- **Static analysis**: `composer analyze` at level 6, 0 errors.
- **Manual verification**: load `examples/5-column-sorting.php` in a browser, click column headers, verify sort order and pagination interaction.

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| **Sort param collision with application params** | Default param names (`sort`, `sort_dir`) are configurable via `setColumnParam()` / `setDirectionParam()`. Document this in the example. |
| **Performance on large datasets** | Native and callback sorts use PHP's `usort()` which is O(n log n). For truly large datasets, consumers should use manual mode with database-level sorting. Document this trade-off. |
| **Sort + pagination interaction** | Sort links must reset the page to 1 (changing sort order invalidates the current page position). Pagination links must preserve current sort params. Verify in tests and example. |
| **Mixed row types during sort** | `sortRows()` partitions `StandardRow` and non-standard rows, sorts only `StandardRow`s, then reassembles. Dedicated test for this. |
| **`$_GET` pollution in tests** | Use `setUp`/`tearDown` to save and restore `$_GET` and `$_SERVER['REQUEST_URI']`, matching the `ArrayPaginationTest` pattern. |
| **`useCallbackSorting` return type mismatch** | Current stub returns `GridColumnInterface`. Changing to `self` is a minor BC break but necessary for fluent API consistency. The interface already declares `self`. |
