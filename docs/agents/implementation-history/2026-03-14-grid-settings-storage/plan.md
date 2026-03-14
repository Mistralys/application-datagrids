# Plan

## Summary

Add a per-grid "items per page" setting backed by a pluggable storage system. Each grid instance gets a mandatory string ID (alias) and a mandatory storage handler. The storage handler is an interface (`GridStorageInterface`) that implementing applications provide; the library ships a default file-based JSON implementation (`JsonFileStorage`). The first user-facing setting is "items per page", which `GridPagination` consults automatically when a provider is attached.

## Architectural Context

### Relevant modules and patterns

- **`DataGrid` / `DataGridInterface`** ([src/Grids/DataGrid.php](src/Grids/DataGrid.php), [src/Grids/DataGridInterface.php](src/Grids/DataGridInterface.php)) — Entry point. Constructor currently accepts `?string $id = null`; auto-generates an ID when none is given. Static factory `DataGrid::create(?string $id = null)`.
- **`GridOptions`** ([src/Grids/Options/GridOptions.php](src/Grids/Options/GridOptions.php)) — Per-grid settings like empty message, repeat header. Natural home for grid-level configuration values.
- **`GridPagination`** ([src/Grids/Pagination/GridPagination.php](src/Grids/Pagination/GridPagination.php)) — Grid-side pagination manager. Delegates to `PaginationInterface` for raw data. Computes derived values (total pages, page numbers).
- **`PaginationInterface`** ([src/Grids/Pagination/PaginationInterface.php](src/Grids/Pagination/PaginationInterface.php)) — Provider contract with `getItemsPerPage()`. The provider is the *data source*; the grid-level setting is a *user preference override*.
- **`ArrayPagination`** ([src/Grids/Pagination/Types/ArrayPagination.php](src/Grids/Pagination/Types/ArrayPagination.php)) — Concrete provider. Has its own `$itemsPerPage` that it uses for slicing. Must be told the effective items-per-page value.
- **Conventions:** Fluent API (setters return `self`), interface + base + concrete types, `declare(strict_types=1)`, classmap autoloading, error codes use `YYMMNN` format.
- **Error code series:** Existing codes are in the `1716xx`–`1717xx` range. New codes should use `260314NN` style (date-based `YYMMNN`).

### Consumer: WebcomicsBuilder (only current user)

Three grid instances exist in the WebcomicsBuilder:

| Page | Grid ID | Pagination |
|---|---|---|
| `ComicBookmarksPage` | *(none — auto-generated)* | No |
| `DetailedListPage` | `'comic-list'` | Yes (`ArrayPagination`) |
| `EpisodeListPage` | `'episode-list'` | Yes (`ArrayPagination`) |

All three will need updating to pass a mandatory grid ID and storage handler.

## Approach / Architecture

### 1. Mandatory grid ID

Make the `$id` parameter in `DataGrid::__construct()` and `DataGrid::create()` a required non-empty `string`. Remove the auto-generation fallback. Enforce **kebab-case** format: `^[a-z][a-z0-9]*(-[a-z0-9]+)*$` (e.g. `comic-list`, `episode-list`). This gives every grid a stable, unique, filesystem-safe alias that the storage layer can use as a key and filename.

### 2. Storage handler interface (`GridStorageInterface`)

A new interface in a new `Storage/` subdirectory under `src/Grids/`. Minimal contract:

```php
interface GridStorageInterface
{
    public function get(string $gridID, string $key, mixed $default = null): mixed;
    public function set(string $gridID, string $key, mixed $value): void;
}
```

- `$gridID` — the mandatory grid alias.
- `$key` — the setting name (e.g. `'items_per_page'`).
- `$default` — fallback when no stored value exists.

This is intentionally generic so future settings (column widths, sort preferences, collapsed states, etc.) slot in naturally.

### 3. Default JSON file storage (`JsonFileStorage`)

A concrete implementation that stores one JSON file per grid in a user-specified folder:

```
<storagePath>/
  comic-list.json
  episode-list.json
```

Each file contains a flat key-value map:

```json
{
    "items_per_page": 50
}
```

Constructor: `__construct(string $storagePath)`. The class creates the directory if it doesn't exist. File operations use `file_get_contents` / `file_put_contents` with `LOCK_EX`.

### 4. Storage as mandatory constructor argument

`DataGrid::__construct(string $id, GridStorageInterface $storage)` — both parameters are required. The grid stores a reference and exposes `getStorage(): GridStorageInterface`.

`DataGrid::create(string $id, GridStorageInterface $storage): static` — updated in lockstep.

`DataGridInterface` gains: `getStorage(): GridStorageInterface`.

### 5. Items-per-page setting

A new class `GridSettings` (in `src/Grids/Options/` or `src/Grids/Settings/`) that wraps storage reads/writes for known settings with typed accessors:

```php
class GridSettings
{
    public function __construct(string $gridID, GridStorageInterface $storage);
    public function getItemsPerPage(?int $default = null): ?int;
    public function setItemsPerPage(int $value): self;
}
```

- `getItemsPerPage(?int $default)` reads from storage, returns the stored value or `$default`.
- `setItemsPerPage(int $value)` writes to storage.

`DataGrid` lazily creates and exposes `settings(): GridSettings`.
`DataGridInterface` gains: `settings(): GridSettings`.

### 6. Integration with pagination

`GridPagination` does **not** automatically override the provider's items-per-page. Instead, the consuming application reads the setting and passes it when constructing the pagination provider:

```php
$itemsPerPage = $grid->settings()->getItemsPerPage(25); // 25 = app default
$pagination = new ArrayPagination($allItems, $itemsPerPage);
$grid->pagination()->setProvider($pagination);
```

This keeps the library non-opinionated about *when* to apply the setting (the app controls it).

### 7. WebcomicsBuilder updates

All three grid creation sites need:
1. A mandatory grid ID (one already missing — `ComicBookmarksPage` needs one, e.g. `'comic-bookmarks'`).
2. A `JsonFileStorage` instance injected into each `DataGrid::create()` call.
3. Paginated grids updated to read `$grid->settings()->getItemsPerPage($default)`.

The `JsonFileStorage` instance should be created once (e.g. in a service/factory or config) and reused across all grids.

## Rationale

- **Interface-based storage** allows the implementing application full control: file storage, database, session, Redis, etc.
- **Mandatory grid ID** enforces uniqueness — without it, settings cannot be reliably linked to a specific grid. Breaking change is acceptable per the user's statement.
- **Mandatory storage handler** makes the dependency explicit — no hidden static/global state. It's the caller's responsibility to provide a configured storage.
- **`GridSettings` as typed wrapper** prevents stringly-typed errors and provides discoverability via IDE autocomplete. Future settings (sort column, column visibility) get added here as methods.
- **Explicit pagination integration** (app reads setting, passes to provider) avoids hidden coupling between the settings system and the pagination provider. The library provides the building blocks; the app assembles them.
- **One JSON file per grid** keeps the file system simple and avoids locking contention across different grids.

## Detailed Steps

### Step 1 — Create `GridStorageInterface`

- **New file:** `src/Grids/Storage/GridStorageInterface.php`
- Namespace: `AppUtils\Grids\Storage`
- Methods: `get(string $gridID, string $key, mixed $default = null): mixed`, `set(string $gridID, string $key, mixed $value): void`

### Step 2 — Create `JsonFileStorage`

- **New file:** `src/Grids/Storage/Types/JsonFileStorage.php`
- Namespace: `AppUtils\Grids\Storage\Types`
- Implements `GridStorageInterface`
- Constructor: `string $storagePath`
- Creates storage directory if missing (`mkdir` with `recursive: true`)
- File naming: `{$storagePath}/{$gridID}.json`
- Grid ID format is guaranteed by `DataGrid` constructor validation (kebab-case); no additional sanitization needed in storage
- Read: `file_get_contents` → `json_decode` → return `$data[$key] ?? $default`
- Write: read existing file → merge → `json_encode` → `file_put_contents` with `LOCK_EX`
- Cache the decoded JSON per grid ID within the request to avoid repeated reads

### Step 3 — Create `GridSettings`

- **New file:** `src/Grids/Settings/GridSettings.php`
- Namespace: `AppUtils\Grids\Settings`
- Constructor: `string $gridID`, `GridStorageInterface $storage`
- Methods:
  - `getItemsPerPage(?int $default = null): ?int` — reads `'items_per_page'` from storage, returns stored value or `$default`
  - `setItemsPerPage(int $value): self` — writes `'items_per_page'` to storage, returns `$this` (fluent)

### Step 4 — Update `DataGridInterface`

- **Modify:** `src/Grids/DataGridInterface.php`
- Add: `use AppUtils\Grids\Storage\GridStorageInterface;`
- Add: `use AppUtils\Grids\Settings\GridSettings;`
- Add method: `getStorage(): GridStorageInterface`
- Add method: `settings(): GridSettings`

### Step 5 — Update `DataGrid`

- **Modify:** `src/Grids/DataGrid.php`
- Change constructor signature: `__construct(string $id, GridStorageInterface $storage)`
  - Remove the `?` nullable and the `null` default from `$id`
  - Remove the auto-ID fallback (`if(empty($id)) { ... }`)
  - Add `$storage` parameter, store it in a private property
  - Validate that `$id` matches the kebab-case regex `^[a-z][a-z0-9]*(-[a-z0-9]+)*$`; throw `DataGridException::ERROR_INVALID_GRID_ID` if not
- Change factory signature: `create(string $id, GridStorageInterface $storage): static`
- Add private property: `GridStorageInterface $storage`
- Add private property: `?GridSettings $settings = null` (lazy)
- Add method: `getStorage(): GridStorageInterface`
- Add method: `settings(): GridSettings` (lazy-creates `GridSettings($this->id, $this->storage)`)

### Step 6 — Update `DataGridException`

- **Modify:** `src/Grids/DataGridException.php`
- Add error code: `ERROR_INVALID_GRID_ID = 260301` (for blank or non-kebab-case ID validation)

### Step 7 — Update examples

- **Modify:** `examples/1-simple-grid.php` — Pass a grid ID and a `JsonFileStorage` instance
- **Modify:** `examples/2-bootstrap-grid.php` — Same
- **Modify:** `examples/3-grid-actions.php` — Same
- **Modify:** `examples/4-pagination.php` — Same; optionally demonstrate `settings()->getItemsPerPage()`

### Step 8 — Update existing tests

All test files that create `DataGrid` instances need updating to pass a mandatory ID and storage handler. A test helper or `InMemoryStorage` (a minimal `GridStorageInterface` implementation for testing that uses an in-memory array) should be created to avoid file I/O in unit tests.

- **New file:** `tests/TestClasses/InMemoryStorage.php` — `GridStorageInterface` implementation backed by an array
- **Update:** All test files that instantiate `DataGrid`

### Step 9 — Add new tests

- **New file:** `tests/Storage/JsonFileStorageTest.php`
  - Test read/write round-trip
  - Test default fallback
  - Test grid ID validation (invalid chars)
  - Test file creation on first write
  - Test concurrent writes (LOCK_EX)
- **New file:** `tests/Settings/GridSettingsTest.php`
  - Test `getItemsPerPage()` with no stored value (returns default)
  - Test `setItemsPerPage()` + `getItemsPerPage()` round-trip
  - Test fluent return

### Step 10 — Update WebcomicsBuilder

- Create a shared `JsonFileStorage` instance (e.g. in `bootstrap.php` or a config property) pointing to a storage folder (e.g. `storage/grid-settings/`)
- **Modify:** `ComicBookmarksPage.php` — Add grid ID `'comic-bookmarks'`, pass storage
- **Modify:** `DetailedListPage.php` — Pass storage; optionally use `settings()->getItemsPerPage()`
- **Modify:** `EpisodeListPage.php` — Pass storage; optionally use `settings()->getItemsPerPage()`

### Step 11 — Run `composer dump-autoload` in both projects

Classmap autoloading requires this after adding new files.

### Step 12 — Run PHPStan and test suite

- `composer analyze` in `application-datagrids`
- `composer test` in `application-datagrids`
- `composer analyze` in `webcomics-builder` (if configured)

## Dependencies

- All new classes (`GridStorageInterface`, `JsonFileStorage`, `GridSettings`) must exist before `DataGrid` can be updated.
- `DataGrid` must be updated before examples, tests, and the WebcomicsBuilder consumer.
- `InMemoryStorage` (test helper) must exist before updating existing tests.

## Required Components

### New files (application-datagrids)

| File | Type | Description |
|---|---|---|
| `src/Grids/Storage/GridStorageInterface.php` | Interface | Storage handler contract |
| `src/Grids/Storage/Types/JsonFileStorage.php` | Class | File-based JSON storage |
| `src/Grids/Settings/GridSettings.php` | Class | Typed settings accessor |
| `tests/TestClasses/InMemoryStorage.php` | Class | In-memory storage for tests |
| `tests/Storage/JsonFileStorageTest.php` | Test | JsonFileStorage tests |
| `tests/Settings/GridSettingsTest.php` | Test | GridSettings tests |

### Modified files (application-datagrids)

| File | Change |
|---|---|
| `src/Grids/DataGridInterface.php` | Add `getStorage()`, `settings()` |
| `src/Grids/DataGrid.php` | Mandatory `$id` + `$storage` constructor params; add `settings()` |
| `src/Grids/DataGridException.php` | Add error codes for ID validation |
| `examples/1-simple-grid.php` | Pass mandatory args |
| `examples/2-bootstrap-grid.php` | Pass mandatory args |
| `examples/3-grid-actions.php` | Pass mandatory args |
| `examples/4-pagination.php` | Pass mandatory args; demo settings |
| All existing test files | Pass mandatory grid ID + `InMemoryStorage` |

### Modified files (webcomics-builder)

| File | Change |
|---|---|
| `assets/classes/Page/ComicBookmarksPage.php` | Add grid ID `'comic-bookmarks'`, pass storage |
| `assets/classes/Page/DetailedListPage.php` | Pass storage |
| `assets/classes/Page/EpisodeListPage.php` | Pass storage |
| Bootstrap or configuration file | Create shared `JsonFileStorage` instance |

## Assumptions

- The grid ID is sufficient as a storage key (one settings file per grid ID). No multi-user / per-user scoping is needed at the library level — the implementing application can handle user-specific storage by providing a user-scoped `GridStorageInterface` implementation.
- The `items_per_page` setting is a *preference stored externally* — it does not replace the provider's `getItemsPerPage()` method. The consuming application is responsible for reading the setting and passing it to the provider.
- JSON is an acceptable format for file-based storage (consistent with webcomics-builder's JSON-only persistence rule).
- Grid IDs are unique within an application. The library does not enforce uniqueness at runtime — it's the developer's responsibility.
- Grid IDs must be kebab-case (`^[a-z][a-z0-9]*(-[a-z0-9]+)*$`). This guarantees filesystem-safe filenames for `JsonFileStorage` and consistency across all storage backends.

## Constraints

- PHP >= 8.4 with `declare(strict_types=1)` in all new files.
- Classmap autoloading — `composer dump-autoload` required after adding files.
- Fluent API — all setters return `self`.
- No constructor property promotion (project convention).
- `HTMLTag` for any HTML output (not applicable here — storage/settings are data-only).
- Error codes follow the `YYMMNN` format.

## Out of Scope

- UI for changing items-per-page (dropdown, form field) — that's a renderer concern for a future plan.
- Per-user storage scoping — the application provides user-specific storage via the interface if needed.
- Other settings (sort column, column visibility, column widths) — these will be added later using the same `GridSettings` class.
- Migration tooling for existing stored data (none exists yet).
- Automatic pagination provider item-count override — the app controls this explicitly.

## Acceptance Criteria

- `DataGrid::create('my-grid', $storage)` requires a non-empty string ID and a `GridStorageInterface` instance.
- `DataGrid::create()` with no arguments, an empty ID, or a non-kebab-case ID throws `DataGridException`.
- `$grid->settings()->setItemsPerPage(50)` persists the value; `$grid->settings()->getItemsPerPage()` returns `50`.
- `$grid->settings()->getItemsPerPage(25)` returns `25` when no value has been stored.
- `JsonFileStorage` creates a JSON file at `{storagePath}/{gridID}.json` on first write.
- `JsonFileStorage` reads back previously written values correctly.
- All existing tests pass after updating to the new constructor signature.
- PHPStan level 6 passes with 0 errors.
- All three WebcomicsBuilder grid instances compile and render correctly with the new signatures.

## Testing Strategy

- **Unit tests for `JsonFileStorage`:** Use a temp directory (`sys_get_temp_dir()`), test CRUD operations, cleanup in `tearDown()`.
- **Unit tests for `GridSettings`:** Use `InMemoryStorage` to test typed read/write/default behavior in isolation.
- **Regression:** Run the full existing test suite to verify no breakage from the constructor change.
- **Static analysis:** `composer analyze` must pass at level 6 with 0 errors.
- **Manual verification:** Load each WebcomicsBuilder page that contains a grid and verify correct rendering.

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| **Breaking change in `DataGrid` constructor** | Only one consumer (WebcomicsBuilder) exists. Update it in the same work package. Breaking changes are explicitly acceptable per the user's statement. |
| **File permission errors in `JsonFileStorage`** | The storage path is developer-provided. Document that the directory must be writable. Throw a clear `DataGridException` if `file_put_contents` fails. |
| **Grid ID collisions** | Document that IDs must be unique per application. The library does not enforce global uniqueness — this is the developer's responsibility. |
| **Grid ID format drift** | Enforced at construction time with a regex check — invalid IDs are rejected immediately with a clear error message. |
| **Test fragility from filesystem I/O** | `InMemoryStorage` for unit tests; `JsonFileStorage` tests use temp directories with cleanup. |
| **Scope creep into UI rendering** | Explicitly out of scope. The plan delivers data-layer storage and typed accessors only. |
