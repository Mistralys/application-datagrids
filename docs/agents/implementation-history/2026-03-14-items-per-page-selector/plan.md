# Plan

## Summary

Add a built-in items-per-page selector to the `application-datagrids` library's pagination system, so that grids can offer users a dropdown to change how many items are shown per page. The selection persists via `GridSettings` (already backed by `GridStorageInterface`). Once the library supports this natively, simplify the `webcomics-builder` consumer to delegate items-per-page resolution to the grid instead of managing it manually.

## Architectural Context

### Existing pieces in the library

| Component | File | Role |
|---|---|---|
| `GridPagination` | `src/Grids/Pagination/GridPagination.php` | Owns pagination state, page numbering, URL templates. No concept of items-per-page options. |
| `GridSettings` | `src/Grids/Settings/GridSettings.php` | Typed key-value accessor backed by `GridStorageInterface`. Already has `getItemsPerPage()` / `setItemsPerPage()`. |
| `PaginationInterface` | `src/Grids/Pagination/PaginationInterface.php` | Contract for providers. Exposes `getItemsPerPage()` — set at construction, immutable. |
| `ArrayPagination` | `src/Grids/Pagination/Types/ArrayPagination.php` | Concrete provider. Accepts `$itemsPerPage` in constructor. |
| `BaseGridRenderer` | `src/Grids/Renderer/BaseGridRenderer.php` | Default renderer. `createPaginationRow()` renders nav + optional page-jump input. |
| `Bootstrap5Renderer` | `src/Grids/Renderer/Types/Bootstrap5Renderer.php` | Bootstrap 5 override. `createBootstrapPaginationRow()` renders `<ul class="pagination">` + page-jump. |
| `DataGrid` | `src/Grids/DataGrid.php` | Orchestrator. `generateOutput()` calls `renderPaginationRow()` in the footer. |

### Existing pieces in the consumer (webcomics-builder)

| Component | File | Role |
|---|---|---|
| `DetailedListPage` | `assets/classes/Page/DetailedListPage.php` | Creates `ArrayPagination` with `$this->resolveItemsPerPage()`, passes to grid's `setProvider()`. Has a manual selector in the filter form. |
| `CardListPage` | `assets/classes/Page/CardListPage.php` | Inherits `_render()` from parent; does NOT use a DataGrid — renders cards manually with `renderPagination()`. |

### Key pattern: lazy resolution from `$_GET`

`SortManager` already follows a lazy-resolution pattern: it reads `$_GET` on first access to `getSortColumn()` / `getSortDir()`. The items-per-page selector should follow the same established pattern.

## Approach / Architecture

### Library changes

Add items-per-page option management to `GridPagination`:

1. **Configuration:** `setItemsPerPageOptions(int[] $options)` — configures the available choices (e.g., `[10, 20, 50, 100]`).
2. **Default:** `setDefaultItemsPerPage(int $default)` — the fallback when nothing is stored.
3. **Resolution:** `resolveItemsPerPage(int $default)` — reads `$_GET[param]` → `GridSettings` → `$default` (in that priority). When a valid `$_GET` value is found, it auto-persists to `GridSettings`. Returns the effective value. This is what applications call to get the value before creating `ArrayPagination`.
4. **URL building:** `getItemsPerPageURLTemplate()` — returns a URL with an `{IPP}` placeholder (same sentinel-replace pattern as `getPageURLTemplate()`). Built from page 1's URL so changing items-per-page resets pagination.
5. **Parameter name:** `setItemsPerPageParam(string $param)` — defaults to `'ipp'`.

Add rendering to `BaseGridRenderer` and `Bootstrap5Renderer`:

6. **`createItemsPerPageSelector()`** — protected method returning an `HTMLTag` `<select>` with `onchange` JS. Each option's value is the numeric items-per-page; `onchange` navigates using the URL template.
7. **Integration into pagination row** — the selector is appended after the page-jump input (when options are configured).
8. **Visibility change** — the pagination row must render when items-per-page options are configured, even if `totalPages <= 1`. The page navigation links are only shown when `totalPages > 1`.

### Consumer changes (webcomics-builder)

9. `DetailedListPage` can use `$grid->pagination()->resolveItemsPerPage(self::ITEMS_PER_PAGE)` instead of manually reading `GridSettings`. The grid's pagination row automatically shows the selector.
10. The manual selector dropdown in `displayFilters()` and the persistence code in `applyFilters()` can be removed (for the DetailedListPage). The `CardListPage` still needs items-per-page from the shared filter form, because it doesn't use a DataGrid. Since both pages share the same `displayFilters()` and `resolveItemsPerPage()`, the filter-form approach should remain — but the DetailedListPage's grid should suppress its own selector to avoid duplication.

**Decision point:** Two options for the consumer:
- **Option A:** Keep the filter-form selector (serves both card and detail views). Tell the grid to NOT show its own selector. The library feature benefits other consumers.
- **Option B:** Remove the filter-form selector. DetailedListPage gets it from the grid. CardListPage gets it from a standalone call to `resolveItemsPerPage()`. This creates an inconsistency in selector placement.

**Recommended: Option A.** The filter-form selector is already working and serves both page types uniformly. The datagrids library feature is still valuable for other applications that only use the grid. The webcomics-builder consumer can simply not call `setItemsPerPageOptions()` on this grid.

## Rationale

- **Follows established patterns:** Lazy `$_GET` resolution (like `SortManager`), URL template with sentinel replacement (like page-jump), protected `create*()` methods for renderer extensibility.
- **Persistence is automatic:** Once options are configured, the grid handles reading `$_GET`, persisting to `GridSettings`, and rendering the selector — no boilerplate for consumers.
- **Backward compatible:** The selector only appears when `setItemsPerPageOptions()` is explicitly called. Existing grids are unaffected.
- **Render visibility change is safe:** Showing the pagination row when `totalPages <= 1` only happens when items-per-page options are configured — otherwise the existing early-return behavior is preserved.

## Detailed Steps

### Step 1 — Symlink the datagrids library in the webcomics-builder

Add a Composer `path` repository to the webcomics-builder's `composer.json` so that the local `application-datagrids` checkout is used instead of the Packagist version. This lets changes in the library be immediately visible in the consumer without publishing.

**File (consumer):** `webcomics-builder/composer.json`

- Add a `"repositories"` key (before `"require"`) pointing to the local datagrids checkout:
  ```json
  "repositories": [
      {
          "type": "path",
          "url": "../../tools/application-datagrids",
          "options": { "symlink": true }
      }
  ]
  ```
- The existing `"mistralys/application-datagrids": "dev-main"` requirement already matches. Run `composer update mistralys/application-datagrids` to switch to the symlink.

> **Cleanup:** Remove or comment out the `repositories` block before committing / when the library changes are published to Packagist.

### Step 2 — `GridPagination`: Add items-per-page state and resolution

**File:** `src/Grids/Pagination/GridPagination.php`

- Add private properties: `$itemsPerPageOptions` (`int[]`, default `[]`), `$defaultItemsPerPage` (`int`, default `25`), `$ippParam` (`string`, default `'ipp'`), `$resolvedItemsPerPage` (`?int`, default `null`).
- Add sentinel constant `IPP_SENTINEL = 888_888_888_888`.
- Add `setItemsPerPageOptions(array $options): self` — stores sorted unique positive integers.
- Add `getItemsPerPageOptions(): array` — returns the options.
- Add `hasItemsPerPageOptions(): bool` — returns `!empty($this->itemsPerPageOptions)`.
- Add `setDefaultItemsPerPage(int $default): self`.
- Add `getDefaultItemsPerPage(): int`.
- Add `setItemsPerPageParam(string $param): self`.
- Add `getItemsPerPageParam(): string`.
- Add `resolveItemsPerPage(?int $default = null): int` — lazy resolution:
  1. If already resolved, return cached value.
  2. Use `$default ?? $this->defaultItemsPerPage` as fallback.
  3. Read `$_GET[$this->ippParam]`.
  4. If present and value is in `$this->itemsPerPageOptions`, persist via `$this->grid->settings()->setItemsPerPage($value)`, cache and return.
  5. Otherwise read from `$this->grid->settings()->getItemsPerPage($fallback)`.
  6. Cache and return.
- Add `getEffectiveItemsPerPage(): int` — alias that calls `resolveItemsPerPage()` (for use in renderer after resolution).
- Add `getItemsPerPageURL(int $itemsPerPage): string` — builds URL by taking `getProvider()->getPageURL(1)`, parsing/rebuilding query string with `$this->ippParam` set to `$itemsPerPage`.
- Add `getItemsPerPageURLTemplate(): string` — calls `getItemsPerPageURL(self::IPP_SENTINEL)` and replaces sentinel with `{IPP}`.

### Step 3 — `BaseGridRenderer`: Add selector rendering

**File:** `src/Grids/Renderer/BaseGridRenderer.php`

- Add `protected createItemsPerPageSelector(GridPagination $pagination): HTMLTag`:
  - Build a `<select>` element.
  - `onchange` JS: `window.location.href = {encodedUrlTemplate}.replace('{IPP}', this.value)` (XSS-safe via `json_encode()`).
  - For each option in `$pagination->getItemsPerPageOptions()`: create an `<option>` with value and label (e.g., `"20 per page"`). Mark the effective one as `selected`.
- Modify `renderPaginationRow()`:
  - Change early-return: render the row when `hasProvider()` is true AND (`totalPages > 1` OR `hasItemsPerPageOptions()`).
- Modify `createPaginationRow()`:
  - Conditionally render the `<nav>` page links only when `$pagination->getTotalPages() > 1`.
  - After the page-jump input, append `createItemsPerPageSelector()` when `$pagination->hasItemsPerPageOptions()`.

### Step 4 — `Bootstrap5Renderer`: Override selector styling

**File:** `src/Grids/Renderer/Types/Bootstrap5Renderer.php`

- Override `createItemsPerPageSelector()`:
  - Add Bootstrap classes to the `<select>`: `form-select form-select-sm`.
  - Wrap in a `<div class="d-flex align-items-center gap-2 mt-2">` container (consistent with the page-jump container styling).
- Modify `renderPaginationRow()`:
  - Apply the same early-return logic change as in `BaseGridRenderer`.
- Modify `createBootstrapPaginationRow()`:
  - Conditionally render the `<nav><ul class="pagination">` only when `$pagination->getTotalPages() > 1`.
  - Append the selector when `$pagination->hasItemsPerPageOptions()`.

### Step 5 — Tests

**File:** `tests/Pagination/GridPaginationTest.php` (extend existing)

Add tests for:
- `hasItemsPerPageOptions()` returns `false` by default.
- `setItemsPerPageOptions()` stores and returns the options.
- `resolveItemsPerPage()` returns the default when no `$_GET` and no stored setting.
- `resolveItemsPerPage()` returns the stored setting from `GridSettings`.
- `resolveItemsPerPage()` reads `$_GET` value, persists it, and returns it.
- `resolveItemsPerPage()` ignores invalid `$_GET` values (not in options list).
- `resolveItemsPerPage()` caches the result (idempotent).
- `getItemsPerPageURLTemplate()` contains `{IPP}` placeholder and no sentinel leak.
- `getItemsPerPageURL()` resets page to 1.

> **Note:** Tests that require `$_GET` manipulation should set/restore `$_GET` directly in the test method (standard PHPUnit pattern for superglobal testing).

### Step 6 — PHPStan & PHPUnit (library)

- Run `composer analyze` — must pass at level 6.
- Run `composer test` — all existing + new tests pass.
- Run `composer dump-autoload` — not needed (no new files/classes added, only modifications).

### Step 7 — Simplify webcomics-builder (optional, recommended: skip)

Per the rationale above (Option A), the webcomics-builder should keep its filter-form selector because `CardListPage` doesn't use a DataGrid. No changes needed in the consumer for this plan — the library feature benefits other consumers.

If desired in a future plan: the `DetailedListPage` could switch to `$grid->pagination()->setItemsPerPageOptions(...)` and use `resolveItemsPerPage()` from the grid. The `CardListPage` would need its own selector approach.

### Step 8 — Update manifest documents (application-datagrids)

| Document | Changes |
|---|---|
| `api-surface.md` | Add new `GridPagination` methods, `IPP_SENTINEL` constant |
| `data-flows.md` | Add items-per-page resolution flow to §5 (Pagination Usage); update render pipeline §2 (footer rendering conditions) |
| `constraints.md` | Update test coverage table with new test count |

## Dependencies

- `GridSettings::getItemsPerPage()` / `setItemsPerPage()` — already implemented.
- `GridStorageInterface` — already injected via `DataGrid`.
- `HTMLTag` from `application-utils-core` — already a dependency.
- `PaginationInterface::getPageURL(1)` — used to build items-per-page URLs with page reset.

## Required Components

- `src/Grids/Pagination/GridPagination.php` — modified
- `src/Grids/Renderer/BaseGridRenderer.php` — modified
- `src/Grids/Renderer/Types/Bootstrap5Renderer.php` — modified
- `tests/Pagination/GridPaginationTest.php` — extended

No new files are created.

## Assumptions

- The `$_GET` superglobal is available during web requests (standard PHP environment).
- The items-per-page URL parameter (`ipp` by default) does not conflict with existing application parameters. Applications can customize it via `setItemsPerPageParam()`.
- When items-per-page changes, resetting to page 1 is the correct behavior (avoids empty-page scenarios).
- The selector label format `"N per page"` is acceptable (not localized in the library; applications needing i18n can override the renderer method).

## Constraints

- **HTMLTag only:** All HTML output via `HTMLTag` — no string concatenation (per `constraints.md`).
- **Fluent API:** All setters return `self`.
- **No constructor property promotion** (per codebase convention).
- **PHPStan level 6** must pass.
- **Backward compatible:** Existing grids that don't call `setItemsPerPageOptions()` see no change in behavior.

## Out of Scope

- Localization/i18n of the "per page" label (renderer override point is sufficient).
- Custom `PaginationInterface` implementations — only `ArrayPagination` is considered as a provider. The feature works with any provider since it only calls `getPageURL(1)`.
- Changes to the webcomics-builder consumer (the library feature is self-contained; consumer simplification is deferred).
- Adding items-per-page options to `PaginationInterface` (the provider's items-per-page is immutable by design; the grid orchestrates the value before provider creation).

## Acceptance Criteria

- [ ] `$grid->pagination()->setItemsPerPageOptions([10, 25, 50, 100])` configures the available choices.
- [ ] `$grid->pagination()->resolveItemsPerPage(25)` returns the effective value (from `$_GET` → `GridSettings` → default).
- [ ] When `$_GET[ipp]` contains a valid option, it is persisted to `GridSettings` automatically.
- [ ] When `$_GET[ipp]` contains an invalid value (not in options), it is ignored.
- [ ] The pagination footer row renders a `<select>` dropdown when options are configured.
- [ ] Changing the selector navigates to page 1 with the new items-per-page parameter.
- [ ] The pagination row renders when options are configured, even if `totalPages <= 1`.
- [ ] The selector correctly marks the current effective value as `selected`.
- [ ] Bootstrap5Renderer styles the selector with Bootstrap 5 form classes.
- [ ] All existing tests continue to pass unchanged.
- [ ] New tests cover resolution logic, URL template, and edge cases.
- [ ] PHPStan level 6 passes with 0 errors.

## Testing Strategy

- **Unit tests** in `GridPaginationTest`: test `resolveItemsPerPage()` with various `$_GET` / `GridSettings` combinations, verify URL template output, verify option configuration.
- **Existing test suite** (`composer test`): all 91+ existing tests must continue to pass — the changes are additive and backward compatible.
- **Static analysis** (`composer analyze`): 0 errors at level 6.
- **Manual verification** (optional): create an example grid with items-per-page options and verify the selector renders correctly in a browser.

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| **`$_GET` pollution:** A query parameter named `ipp` could collide with application parameters. | Parameter name is configurable via `setItemsPerPageParam()`. Default `ipp` is short and unlikely to conflict. |
| **Render change breaks existing layouts:** Showing the pagination row when `totalPages <= 1` could surprise consumers. | Only triggers when `hasItemsPerPageOptions()` is true — requires explicit opt-in. Existing grids are unaffected. |
| **XSS in onchange JS:** URL template could contain malicious characters. | URL template is `json_encode()`'d (same proven pattern as page-jump input). |
| **Race condition on `$_GET` + `GridSettings`:** Two tabs changing items-per-page simultaneously. | Last-write-wins, same as all other settings. Acceptable for a single-user tool. |
