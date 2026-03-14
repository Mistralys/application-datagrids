# Plan — Grid Actions & Pagination: Post-Implementation Rework

## Summary

This plan addresses all actionable carry-forward items from the [2026-03-09 Grid Actions & Pagination synthesis](../2026-03-09-grid-actions-pagination/synthesis.md): 7 known bugs, 9 architecture debt items, and the full testing gap. Work is grouped into four logical phases: critical bug fixes, architecture debt resolution, accessibility improvements, and unit test suite creation.

## Architectural Context

The changes span the following existing modules:

| Module | Key Files | Relevance |
|---|---|---|
| Core | `src/Grids/DataGrid.php`, `src/Grids/DataGridInterface.php` | Add `hasActions()`, `processActions()`, fix null-check style |
| Actions | `src/Grids/Actions/GridActions.php`, `src/Grids/Actions/Type/RegularAction.php` | Fix `processSubmittedActions()` signature, callback return type |
| Renderer | `src/Grids/Renderer/BaseGridRenderer.php`, `src/Grids/Renderer/Types/Bootstrap5Renderer.php`, `src/Grids/Renderer/GridRendererInterface.php` | Fix JS scoping, XSS, dead import, option attributes, accessibility |
| Pagination | `src/Grids/Pagination/GridPagination.php` | Replace magic sentinel |
| Rows | `src/Grids/Rows/Types/StandardRow.php` | Add developer warning for empty select values |
| Cells | `src/Grids/Cells/SelectionCell.php` | Remove constructor property promotion |
| Examples | `examples/3-grid-actions.php` | Fix feedback race condition |
| Tests | `tests/` (new directory) | Full unit test suite |

Patterns to follow: fluent API (setters return `$this`), `HTMLTag` for all HTML output, interface-driven design, `declare(strict_types=1)`, trait+interface pairing.

## Approach / Architecture

### Phase 1 — Critical Bug Fixes (Bugs #1, #3, #4)

Fix the three HIGH-severity bugs first as they affect runtime correctness and security:

1. **Bug #1 — Select-all JS scoping**: Change `createSelectionHeaderCell()` in `BaseGridRenderer` to scope the `querySelectorAll` call to the grid's table container using `closest('table')` or equivalent DOM traversal, so "select all" only affects checkboxes within its own grid.

2. **Bug #3 — XSS in page jump inputs**: In both `BaseGridRenderer::createPageJumpInput()` and `Bootstrap5Renderer::createBootstrapPageJumpInput()`, replace the raw single-quoted JS string interpolation of `$urlTemplate` with `json_encode($urlTemplate)` to produce a safely-escaped JS string literal.

3. **Bug #4 — Example feedback race condition**: In `examples/3-grid-actions.php`, call `$grid->actions()->processSubmittedActions()` explicitly **before** the HTML output begins. Move the `$feedback` conditional block to render after the callback has had a chance to fire.

### Phase 2 — Medium & Low Bug Fixes (Bugs #2, #5, #6, #7)

4. **Bug #2 — Placeholder option attributes**: In `BaseGridRenderer::renderActionsRow()`, add `disabled` and `selected` attributes to the placeholder `<option value="">`.

5. **Bug #5 — Explicit action processing**: Add `DataGrid::processActions(): bool` as a public method that calls `$this->actions()->processSubmittedActions()` if actions are configured and returns the result. This lets callers control when side effects occur (before rendering). Keep the lazy invocation in `generateOutput()` as a safety net, but make it idempotent (skip if already processed).

6. **Bug #6 — Empty select value warning**: In `StandardRow::getSelectionCell()`, when `isSelectable()` is `true` but `getSelectValue()` returns `''`, trigger a `trigger_error()` with `E_USER_WARNING` so developers get a diagnostic message. Do not throw — this preserves backward compatibility.

7. **Bug #7 — Magic sentinel replacement**: In `GridPagination::getPageURLTemplate()`, replace the numeric sentinel `999999999` with a non-numeric string sentinel `__PAGE_NUMBER__` that cannot plausibly appear in a real URL. Update the JS interpolation in both renderers to use the new sentinel.

### Phase 3 — Architecture Debt

8. **Interface decoupling for constructors**: Change `GridActions::__construct()` and `GridPagination::__construct()` to accept `DataGridInterface` instead of `DataGrid`. This requires adding `columns(): ColumnManager` to `DataGridInterface` (already present — confirmed in api-surface) and ensuring all internal usages go through the interface.

9. **Add `hasActions(): bool` to `DataGridInterface`**: Add this method to the interface and implement it in `DataGrid` to check `$this->actions !== null && $this->actions->hasActions()` without triggering lazy instantiation. Update `BaseGridRenderer::getColspan()` and `renderHeaderCells()` to use it.

10. **Fix `processSubmittedActions()` signature**: Change `GridActions::processSubmittedActions(array $postData = [])` to `GridActions::processSubmittedActions(?array $postData = null)` and use `$postData ?? $_POST` internally. This eliminates the ambiguity where `[]` is indistinguishable from "use `$_POST`".

11. **Native return type on `RegularAction::getCallback()`**: Change from PHPDoc-only `@return callable|null` to native `callable|null` return type (PHP 8.4 supports this).

12. **Remove dead import**: Remove `use AppUtils\Grids\Actions\Type\GridActionInterface;` from `BaseGridRenderer.php`.

13. **Remove constructor property promotion in `SelectionCell`**: Replace `private StandardRow $row` constructor promotion with explicit assignment `$this->row = $row` to match codebase conventions.

14. **Unify null-check style in `DataGrid::generateOutput()`**: Standardize on `$this->actions !== null` (not `isset($this->actions)`) throughout the method.

15. **Bootstrap5 pagination accessibility**: Add `aria-current="page"` to the active page `<li>` item and `aria-label="Previous page"` / `aria-label="Next page"` to the prev/next `<a>` elements in `Bootstrap5Renderer`.

### Phase 4 — Unit Test Suite

16. **Create test infrastructure**: Add `tests/` directory, create `phpunit.xml` (or verify existing config), create a `TestCase` base class if needed.

17. **Pagination tests** (`tests/Pagination/GridPaginationTest.php`):
    - `getPageNumbers()` — ellipsis logic, edge/adjacent window overlap, edge cases at 0 and 1 total pages
    - `getTotalPages()`, `getCurrentPage()` — clamping and zero-item boundary
    - Sentinel replacement in `getPageURLTemplate()`

18. **ArrayPagination tests** (`tests/Pagination/ArrayPaginationTest.php`):
    - `getSlicedItems()` — page 1, middle page, last page, single-item last page
    - `getPageURL()` — adds page param when absent, replaces when present

19. **Action processing tests** (`tests/Actions/GridActionsTest.php`):
    - No POST data → returns false
    - Missing action field → returns false
    - Unknown action name → returns false
    - `SeparatorAction` skipping
    - Callback invocation with correct selected values
    - `?array $postData = null` signature behaviour

20. **Row & cell tests** (`tests/Rows/StandardRowTest.php`, `tests/Cells/SelectionCellTest.php`):
    - `getSelectValue()` — with and without value column
    - `SelectionCell::renderContent()` — correct `name` and `value` attributes

## Rationale

- **Phase ordering** prioritizes HIGH-severity bugs (security/correctness) first, then MEDIUM/LOW bugs, then debt, then tests. This is deliberate: the test suite in Phase 4 validates the fixes from Phases 1–3.
- **`processActions()` over removing lazy invocation**: Keeping the lazy call in `generateOutput()` as a safety net prevents silent regressions for users who don't call `processActions()` explicitly. The idempotency guard makes both paths safe.
- **`trigger_error` over exception for Bug #6**: An exception would break existing consumers who happen to have actions without a value column. A warning is diagnostic without being destructive.
- **Non-numeric sentinel for Bug #7**: A string like `__PAGE_NUMBER__` is entirely unambiguous in a URL context, whereas any integer sentinel can collide with real data.
- **Interface decoupling**: Accepting `DataGridInterface` instead of `DataGrid` enables mock-based testing and follows the existing interface-driven design principle.

## Detailed Steps

### Phase 1 — Critical Bug Fixes

1. **Fix select-all JS scoping** in `src/Grids/Renderer/BaseGridRenderer.php`:
   - In `createSelectionHeaderCell()`, change the `onclick` JS from `document.querySelectorAll('input[name="selected[]"]')` to use `this.closest('table').querySelectorAll('input[name="selected[]"]')` so the toggle is scoped to the containing table element.

2. **Fix XSS in page jump inputs**:
   - In `src/Grids/Renderer/BaseGridRenderer.php` → `createPageJumpInput()`: replace `'{$urlTemplate}'` with the output of `json_encode($urlTemplate)` (no wrapping quotes — `json_encode` produces them).
   - In `src/Grids/Renderer/Types/Bootstrap5Renderer.php` → `createBootstrapPageJumpInput()`: same fix.

3. **Fix example feedback race condition** in `examples/3-grid-actions.php`:
   - After configuring the grid and adding actions, call `$grid->actions()->processSubmittedActions()` before any HTML output.
   - Move the `$feedback` conditional display block to after the callback has been invoked.

### Phase 2 — Medium & Low Bug Fixes

4. **Fix placeholder option** in `src/Grids/Renderer/BaseGridRenderer.php` → `renderActionsRow()`:
   - Add `disabled` and `selected` HTML attributes to the placeholder `<option value="">`.

5. **Add `DataGrid::processActions(): bool`** in `src/Grids/DataGrid.php`:
   - Public method: if `$this->actions !== null`, delegates to `$this->actions->processSubmittedActions()` and returns the result; otherwise returns `false`.
   - Add a `private bool $actionsProcessed = false` flag. Set to `true` after processing.
   - In `generateOutput()`, check `$this->actionsProcessed` before calling `processSubmittedActions()` again (idempotency).
   - Add `processActions(): bool` to `DataGridInterface`.

6. **Add developer warning for empty select value** in `src/Grids/Rows/Types/StandardRow.php`:
   - In `getSelectionCell()`, after confirming `isSelectable()` is true, check if `getSelectValue()` === `''`. If so, `trigger_error('DataGrid: Row selection is active but no value column is configured. Checkboxes will submit empty values.', E_USER_WARNING)`.

7. **Replace magic sentinel** in `src/Grids/Pagination/GridPagination.php`:
   - Change the sentinel constant from `999999999` to a class constant `private const PAGE_SENTINEL = '__PAGE_NUMBER__'`.
   - In `getPageURLTemplate()`: use `self::PAGE_SENTINEL` for the sentinel, and `str_replace(self::PAGE_SENTINEL, '{PAGE}', ...)` to produce the final template.
   - The provider's `getPageURL()` is called with the sentinel value — since it's now a string, ensure it's cast to int appropriately or pass it as a string. **Important**: `PaginationInterface::getPageURL(int $page)` expects an `int`. This means we still need to use a numeric sentinel for the call to `getPageURL()`, but we can use a much more distinctive one or document the approach. **Revised approach**: Keep a large numeric sentinel for the `getPageURL(int)` call, but make it a named class constant (`private const URL_SENTINEL = 999_999_999_999`) and document it. Then `str_replace` the stringified sentinel with `{PAGE}` in the result. The key difference from the current code: use a 12-digit number instead of 9-digit, reducing collision risk significantly, and name it as a constant for clarity. Alternatively, restructure to avoid the sentinel entirely by constructing the URL template manually from the provider's URL pattern — but this is out of scope for a rework and would require `PaginationInterface` changes.

   **Final approach**: Change to a named class constant `private const PAGE_SENTINEL = 999_999_999_999` (12 digits — virtually impossible to collide with real URL parameters). Update `getPageURLTemplate()` to use `self::PAGE_SENTINEL`. The sentinel is used only internally to call `getPageURL(self::PAGE_SENTINEL)` and then `str_replace((string)self::PAGE_SENTINEL, '{PAGE}', $url)`. This is a low-risk, minimal change.

### Phase 3 — Architecture Debt

8. **Decouple `GridActions` and `GridPagination` constructors**:
   - Change `GridActions::__construct(DataGrid $grid)` → `GridActions::__construct(DataGridInterface $grid)`.
   - Change `GridPagination::__construct(DataGrid $grid)` → `GridPagination::__construct(DataGridInterface $grid)`.
   - Update property types and `getGrid()` return types accordingly.
   - Verify that `columns(): ColumnManager` is already on `DataGridInterface` (confirmed — it is).

9. **Add `hasActions(): bool` to `DataGridInterface` and `DataGrid`**:
   - `DataGrid::hasActions(): bool` → `return $this->actions !== null && $this->actions->hasActions()`.
   - Add to `DataGridInterface`.
   - Update `BaseGridRenderer::getColspan()` to use `$this->grid->hasActions()` instead of `$this->grid->actions()->hasActions()`.
   - Update `BaseGridRenderer::renderHeaderCells()` (the guard that calls `renderSelectionHeaderCell()`) similarly.

10. **Fix `processSubmittedActions()` signature**:
    - `GridActions::processSubmittedActions(?array $postData = null): bool`.
    - Internally: `$postData = $postData ?? $_POST;`.

11. **Native return type on `RegularAction::getCallback()`**:
    - Change `public function getCallback()` → `public function getCallback(): ?callable`.
    - Remove the `@return callable|null` PHPDoc line (the native type is sufficient).

12. **Remove dead import** from `src/Grids/Renderer/BaseGridRenderer.php`:
    - Delete `use AppUtils\Grids\Actions\Type\GridActionInterface;`.

13. **Fix `SelectionCell` constructor style** in `src/Grids/Cells/SelectionCell.php`:
    - Replace `public function __construct(private StandardRow $row)` with explicit `private StandardRow $row;` property declaration and `$this->row = $row;` in the constructor body.

14. **Unify null-check in `DataGrid::generateOutput()`**:
    - Replace `isset($this->actions)` with `$this->actions !== null`.

15. **Add aria attributes to Bootstrap5 pagination** in `src/Grids/Renderer/Types/Bootstrap5Renderer.php`:
    - Active page `<li>`: add `aria-current="page"`.
    - Previous link `<a>` or `<span>`: add `aria-label="Previous page"`.
    - Next link `<a>` or `<span>`: add `aria-label="Next page"`.

### Phase 4 — Unit Test Suite

16. **Create test infrastructure**:
    - Create `tests/` directory.
    - Create `tests/bootstrap.php` (require Composer autoloader).
    - Create or update `phpunit.xml` at project root with `<testsuites>` configuration pointing to `tests/`.
    - Verify `composer test` runs correctly.

17. **Create `tests/Pagination/GridPaginationTest.php`**:
    - Test `getTotalPages()` with various item counts and items-per-page.
    - Test `getCurrentPage()` clamping: page 0 → 1, page > totalPages → totalPages.
    - Test `getPageNumbers()` output for small (≤7 pages), medium, and large page counts. Verify `null` entries represent ellipsis sentinels.
    - Test `hasPreviousPage()` / `hasNextPage()` at boundaries.
    - Test `getPageURLTemplate()` uses the named sentinel constant.

18. **Create `tests/Pagination/ArrayPaginationTest.php`**:
    - Test `getSlicedItems()` for page 1, a middle page, and the last page.
    - Test single-item last page slice.
    - Test `getPageURL()` adds the page param when absent in the URL.
    - Test `getPageURL()` replaces the page param when already present.

19. **Create `tests/Actions/GridActionsTest.php`**:
    - Test `processSubmittedActions(null)` with empty `$_POST` → returns `false`.
    - Test with explicit post data missing the action field → returns `false`.
    - Test with an unknown action name → returns `false`.
    - Test that `SeparatorAction` entries are skipped.
    - Test that the correct callback is invoked with the expected `$selectedValues` array.
    - Test that passing `null` uses `$_POST` and passing `[]` is treated as an empty array (not a fallback).

20. **Create `tests/Rows/StandardRowTest.php` and `tests/Cells/SelectionCellTest.php`**:
    - `StandardRowTest`: test `getSelectValue()` returns the correct cell value when a value column is set. Test it returns `''` when no value column is configured.
    - `SelectionCellTest`: test `renderContent()` produces an `<input>` with the correct `name="selected[]"` attribute and the expected `value`.

## Dependencies

- Phase 2 Step 5 (`processActions()`) should be done before Phase 4 Step 19 (action tests), so the test suite can exercise the new public API.
- Phase 3 Step 10 (signature change) should be done before Phase 4 Step 19, so tests use the final `?array` signature.
- Phase 3 Step 9 (`hasActions()`) must be done after Step 8 (interface decoupling), since it adds another method to `DataGridInterface`.
- Phase 4 Step 16 (test infra) must precede Steps 17–20.
- All other steps within each phase are independent and can be parallelized.

## Required Components

### Modified Files
- `src/Grids/DataGrid.php` — add `processActions()`, `hasActions()`, unify null checks, idempotency flag
- `src/Grids/DataGridInterface.php` — add `processActions()`, `hasActions()`
- `src/Grids/Actions/GridActions.php` — change constructor type, fix `processSubmittedActions()` signature
- `src/Grids/Actions/Type/RegularAction.php` — native `?callable` return type
- `src/Grids/Cells/SelectionCell.php` — remove constructor promotion
- `src/Grids/Rows/Types/StandardRow.php` — add developer warning
- `src/Grids/Pagination/GridPagination.php` — named sentinel constant
- `src/Grids/Renderer/BaseGridRenderer.php` — JS scoping fix, XSS fix, option fix, dead import removal, use `hasActions()`
- `src/Grids/Renderer/Types/Bootstrap5Renderer.php` — XSS fix, aria attributes
- `src/Grids/Renderer/GridRendererInterface.php` — no expected changes (already correct)
- `examples/3-grid-actions.php` — fix feedback race condition

### New Files
- `tests/bootstrap.php`
- `tests/Pagination/GridPaginationTest.php`
- `tests/Pagination/ArrayPaginationTest.php`
- `tests/Actions/GridActionsTest.php`
- `tests/Rows/StandardRowTest.php`
- `tests/Cells/SelectionCellTest.php`

### Updated Manifest Documents
- `docs/agents/project-manifest/api-surface.md` — `DataGridInterface` gains `hasActions()`, `processActions()`; `GridActions` signature change; `RegularAction` return type; `GridPagination` constructor type
- `docs/agents/project-manifest/constraints.md` — remove fixed bugs from Known Bugs table; update stub inventory if applicable
- `docs/agents/project-manifest/data-flows.md` — update action processing flow to reflect `processActions()` and idempotency
- `docs/agents/project-manifest/file-tree.md` — add `tests/` directory tree

## Assumptions

- PHP 8.4 runtime supports `?callable` as a native return type (confirmed).
- The `phpunit.xml` at the project root is either missing or can be created/updated; `composer test` is already wired to `vendor/bin/phpunit`.
- `trigger_error()` is an acceptable developer-warning mechanism (no custom logger is in use).
- The `HTMLTag::attr()` method supports adding `disabled` and `selected` attributes (standard in `application-utils-core`).
- The 12-digit sentinel `999_999_999_999` fits in a PHP `int` on 64-bit systems (it does — max int is ~9.2×10¹⁸).

## Constraints

- All new files must include `declare(strict_types=1);`.
- All setters must return `self`/`$this` for fluent API.
- All HTML output must use `HTMLTag` — no raw string concatenation.
- `composer dump-autoload` must be run after adding test files (classmap autoloading).
- PHPStan level 6 must pass with zero new errors after all changes.
- The namespace anomaly (`WebcomicsBuilder`) must NOT be fixed unless explicitly requested.

## Out of Scope

- Fixing the `WebcomicsBuilder` namespace anomaly.
- Implementing the remaining stubs (`getSortColumn()`, `getSortDir()`, `useCallbackSorting()`, `useManualSorting()`, `renderCustomRow()`).
- Adding `PaginationInterface` changes to avoid the sentinel pattern entirely (would be a breaking API change).
- Performance optimization or caching.
- CI/CD pipeline setup.
- Adding a custom PSR-3 logger (the `trigger_error` approach is sufficient for now).

## Acceptance Criteria

1. All 7 known bugs from the synthesis are resolved.
2. All 9 architecture debt items are resolved.
3. PHPStan level 6 reports zero new errors across all modified/created files.
4. `composer test` runs and all tests pass.
5. Unit tests cover: pagination page number calculation, array slicing, URL generation, action processing (positive + negative cases), row selection values, and selection cell rendering.
6. `examples/3-grid-actions.php` correctly displays feedback after an action is submitted.
7. "Select all" checkbox in a multi-grid page only affects its own grid.
8. Page jump input is safe against XSS via URL template values.
9. All manifest documents are updated to reflect the changes.

## Testing Strategy

- **Unit tests** (Phase 4) are the primary verification method. Each test class targets a specific component and covers both happy-path and edge-case scenarios.
- **PHPStan** (`composer analyze`) validates type safety after each phase.
- **Manual browser testing** for Bugs #1 (multi-grid select-all), #3 (page jump XSS), and #4 (example feedback) — create a temporary multi-grid example page to verify the JS scoping fix.
- **Regression check**: Run `composer analyze` and `composer test` after each phase to catch regressions early.

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| **`processSubmittedActions()` signature change is breaking** | The change from `array $postData = []` to `?array $postData = null` is backward-compatible for callers using the default (no argument). Callers explicitly passing `[]` will now get different behaviour (empty array instead of `$_POST` fallback) — this is the *intended* fix. Document in changelog. |
| **`GridActions`/`GridPagination` constructor type change breaks subclasses** | No known subclasses exist outside this codebase. The classmap autoloader scans only `src/`. Low risk. |
| **12-digit sentinel still theoretically collides** | Risk is negligible (1 in ~10¹² for any single URL parameter). Document the constant's purpose via PHPDoc. |
| **`trigger_error` ignored in production** | Most PHP production configs still report `E_USER_WARNING`. Document the warning in the API surface. Developers who suppress warnings accept the empty-value risk. |
| **Test suite depends on `$_GET`/`$_POST` superglobals** | Tests that exercise `ArrayPagination` or `processSubmittedActions()` must explicitly pass data via parameters rather than relying on superglobals. The `?array` signature change enables this cleanly. |
| **`aria-current` not supported by older screen readers** | ARIA 1.1 attribute; supported by all modern assistive technologies. Acceptable trade-off. |
