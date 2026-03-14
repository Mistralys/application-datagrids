# Plan — Grid Actions & Pagination

## Summary

Complete the partially-implemented grid actions feature (row selection checkboxes + action callbacks) and add a new pagination system to the data grid library. Grid actions will auto-invoke registered PHP callbacks when the form is submitted. Pagination will use a provider interface for maximum flexibility across storage paradigms, with a bundled array-based implementation for convenience and examples.

---

## Current State Assessment

### Grid Actions — Partially Implemented

| Component | Status | Notes |
|---|---|---|
| `GridActions` class | Partial | `setValueColumn()`, `add()`, `separator()`, `hasActions()` work. `render()` is an empty stub. |
| `RegularAction` | Partial | Stores name + label. **No callback support.** |
| `SeparatorAction` | Complete | Marker class, works as-is. |
| `GridActionInterface` | Complete | Empty marker interface. |
| `SelectionCell` | Partial | Renders checkbox with `name="selected[]"`, but calls `getRow()->getSelectValue()` which **does not exist** on `StandardRow`. |
| `StandardRow::isSelectable()` | Complete | Returns `true` when actions are defined. |
| `StandardRow::getSelectionCell()` | **Stub** | Empty method body. |
| `StandardRow::getSelectValue()` | **Missing** | Called by `SelectionCell` but never defined. |
| `GridActions::getValueColumn()` | **Missing** | Value column is stored internally but not exposed via getter. |
| `BaseGridRenderer::renderActionsRow()` | Partial | Renders `<select>` dropdown but **no `name` attribute**, **no submit button**. |
| `BaseGridRenderer::renderStandardRowCells()` | Partial | Checks `isSelectable()` and calls `getSelectionCell()` but **never renders the selection cell**. |
| Header selection checkbox | **Missing** | No "select all" checkbox in the header row. |
| Action processing (POST) | **Missing** | No server-side form submission handling. |
| Form wrapping | Complete | `GridForm` + `<form method="post">` rendering works. |
| Example `3-grid-actions.php` | Partial | Configures actions but cannot function due to stubs. |

### Pagination — Not Started

No pagination-related code exists anywhere in the codebase. The `GridFooter` is a bare classable/ID-able container. The footer rendering pipeline has slots for repeated headers and actions but no pagination.

---

## Architectural Context

### Rendering Pipeline (current footer section in `DataGrid::generateOutput()`)

```
renderFooterTop(footer)         → <tfoot>
  renderHeaderRowRepeated(...)  → optional repeated header
  renderActionsRow(actions)     → optional action dropdown
renderFooterBottom(footer)      → </tfoot>
```

### Key Patterns to Follow

- **Interface → Abstract Base → Concrete Types** (e.g., `GridColumnInterface → BaseGridColumn → DefaultColumn`)
- **Trait + matching Interface** for shared behaviors (e.g., `AlignTrait ↔ AlignInterface`)
- **Manager/wrapper classes** for sub-components (e.g., `ColumnManager`, `RowManager`, `RendererManager`)
- **Fluent API** — all setters return `self`/`$this`
- **`HTMLTag`** for all HTML generation — no string concatenation
- **`declare(strict_types=1)`** in every file
- **Lazy initialization** for optional sub-components (e.g., `GridActions` via `DataGrid::actions()`)
- **Classmap autoloading** — run `composer dump-autoload` after adding files

### Relevant Files

- [src/Grids/DataGrid.php](src/Grids/DataGrid.php) — main entry point, `generateOutput()` orchestration
- [src/Grids/DataGridInterface.php](src/Grids/DataGridInterface.php) — public contract
- [src/Grids/Actions/GridActions.php](src/Grids/Actions/GridActions.php) — action manager
- [src/Grids/Actions/Type/RegularAction.php](src/Grids/Actions/Type/RegularAction.php) — action definition
- [src/Grids/Actions/Type/GridActionInterface.php](src/Grids/Actions/Type/GridActionInterface.php) — action type marker
- [src/Grids/Cells/SelectionCell.php](src/Grids/Cells/SelectionCell.php) — checkbox cell
- [src/Grids/Rows/Types/StandardRow.php](src/Grids/Rows/Types/StandardRow.php) — data row (namespace anomaly: `WebcomicsBuilder`)
- [src/Grids/Renderer/BaseGridRenderer.php](src/Grids/Renderer/BaseGridRenderer.php) — default rendering logic
- [src/Grids/Renderer/GridRendererInterface.php](src/Grids/Renderer/GridRendererInterface.php) — renderer contract
- [src/Grids/Renderer/Types/Bootstrap5Renderer.php](src/Grids/Renderer/Types/Bootstrap5Renderer.php) — Bootstrap renderer
- [src/Grids/Renderer/Types/DefaultRenderer.php](src/Grids/Renderer/Types/DefaultRenderer.php) — plain HTML renderer
- [src/Grids/Footer/GridFooter.php](src/Grids/Footer/GridFooter.php) — footer container
- [src/Grids/Options/GridOptions.php](src/Grids/Options/GridOptions.php) — grid options
- [examples/3-grid-actions.php](examples/3-grid-actions.php) — actions example

---

## Approach / Architecture

### Feature 1: Grid Actions (Complete the Implementation)

**Goal:** Enable row selection via checkboxes, action selection via dropdown, form submission, and auto-invocation of a registered PHP callback with the selected row primary keys.

#### Data flow:

```
1. Configuration phase:
   $grid->actions()->setValueColumn('id');
   $grid->actions()->add('delete', 'Delete selected')
       ->setCallback(function(array $selectedIDs): void { ... });

2. Rendering phase:
   - Header row gets a "select all" checkbox <th> prepended
   - Each StandardRow gets a SelectionCell <td> prepended (checkbox with value from value column)
   - Footer gets actions row: <select name="grid_action"> + <button type="submit">
   - Colspan calculations account for the extra selection column (+1)

3. Submission phase (auto on next render):
   DataGrid::generateOutput()
     → GridActions::processSubmittedActions()
       → Reads $_POST['grid_action'] and $_POST['selected']
       → Matches action name to registered RegularAction
       → If action has a callback, invokes it with selected values array
       → Returns true (action was processed)
```

#### Changes by file:

| File | Change |
|---|---|
| `RegularAction` | Add `setCallback(callable): self`, `getCallback(): ?callable`, `hasCallback(): bool` |
| `GridActions` | Add `getValueColumn(): ?GridColumnInterface`, `processSubmittedActions(): bool`, `getFormActionFieldName(): string`, `getFormSelectionFieldName(): string` |
| `GridActions::render()` | Remove stub (rendering is handled by the renderer) or implement delegation to the renderer |
| `StandardRow` | Add `getSelectValue(): string` (reads value from the value column), implement `getSelectionCell()` (creates/caches `SelectionCell`) |
| `SelectionCell` | Already functional once `getSelectValue()` exists. No changes needed. |
| `BaseGridRenderer::renderStandardRowCells()` | Render the selection cell `<td>` before regular cells |
| `BaseGridRenderer::renderHeaderCells()` | Prepend a "select all" checkbox `<th>` when actions are enabled |
| `BaseGridRenderer::renderActionsRow()` | Add `name="grid_action"` to `<select>`, add a submit `<button>`, update colspan to account for selection column |
| `BaseGridRenderer` | Add new protected methods: `createSelectionHeaderCell()`, `createSelectionCell(SelectionCell)`, `getColspan(): int` (accounts for selection column) |
| `DataGrid::generateOutput()` | Call `processSubmittedActions()` before rendering |
| `DataGridInterface` | Add `actions(): GridActions` to the interface (currently only on `DataGrid`) |
| Example `3-grid-actions.php` | Update to register callbacks and demonstrate working actions |

### Feature 2: Pagination

**Goal:** Configurable pagination controls in the grid footer, driven by an application-provided data source, with a bundled array-slicing implementation.

#### Architecture:

```
PaginationInterface          ← App implements this (or uses ArrayPagination)
  │
  └─ ArrayPagination         ← Built-in: wraps an array, slices it, builds URLs from query params
  
GridPagination               ← Grid-side manager (like GridActions)
  │  wraps PaginationInterface
  │  computes: total pages, page number ranges, navigation state
  │
  └─ Used by Renderer        ← renderPaginationRow() in footer
```

#### New directory: `src/Grids/Pagination/`

| New File | Type | Description |
|---|---|---|
| `PaginationInterface.php` | Interface | Contract: `getTotalItems()`, `getItemsPerPage()`, `getCurrentPage()`, `getPageURL(int $page): string` |
| `GridPagination.php` | Class | Grid-side wrapper. Holds a `PaginationInterface` provider. Computes total pages, page number ranges (optimized for large sets with ellipsis), first/last/prev/next state. Configuration: adjacent page count, page jump enabled. |
| `Types/ArrayPagination.php` | Class | Built-in implementation. Constructor takes a full data array + items per page. Slices array for current page. Builds URLs by replacing/adding a query parameter on the current request URI. Provides `getSlicedItems(): array`. |

#### Page number algorithm (in `GridPagination`):

For large result sets, generate a compact page list:
```
Total 100 pages, current = 50, adjacent = 2:
→ [1, 2, null, 48, 49, 50, 51, 52, null, 99, 100]
   ↑first    ↑gap  ↑adjacent window   ↑gap  ↑last

Where null = ellipsis separator
```

Always show: first N pages, last N pages, current page ± adjacent count. Use `null` entries to represent ellipsis gaps.

#### Jump-to-page:

The renderer generates a small input with JavaScript that uses a URL template derived by `GridPagination`:
```php
// GridPagination computes the template by calling:
$url = $provider->getPageURL(999999999);
$template = str_replace('999999999', '{PAGE}', $url);
```

The rendered HTML:
```html
<input type="number" min="1" max="100" id="grid-{id}-page-jump">
<button onclick="var p=document.getElementById('grid-{id}-page-jump').value; 
  window.location.href='{template}'.replace('{PAGE}', p);">Go</button>
```

#### Rendering integration:

| File | Change |
|---|---|
| `GridRendererInterface` | Add `renderPaginationRow(GridPagination $pagination): string\|StringableInterface` |
| `BaseGridRenderer` | Implement default `renderPaginationRow()`: page links list + jump input in a `<tr><td colspan>` |
| `BaseGridRenderer` | Add protected helpers: `createPaginationRow()`, `createPageLink()`, `createPageJumpInput()`, `createEllipsis()` |
| `Bootstrap5Renderer` | Override `renderPaginationRow()` for Bootstrap 5 pagination component styling (`<nav><ul class="pagination">`) |
| `DefaultRenderer` | Inherits base implementation (plain HTML) |
| `DataGrid` | Add `pagination(): GridPagination` (lazy-init), integrate into `generateOutput()` footer section |
| `DataGridInterface` | Add `pagination(): GridPagination` |
| `GridFooter` | No structural changes (remains a classable container) |

#### Footer rendering flow (updated):

```
renderFooterTop(footer)                → <tfoot>
  renderHeaderRowRepeated(...)         → optional repeated header
  renderPaginationRow(pagination)      → optional pagination controls    ← NEW
  renderActionsRow(actions)            → optional action dropdown
renderFooterBottom(footer)             → </tfoot>
```

Pagination above actions: the page navigation is more prominent; the bulk actions row with its submit button sits at the very bottom of the footer.

#### Example file: `examples/4-pagination.php` (NEW)

See Step 17 in Detailed Steps for the full example specification. Demonstrates:
- Creating a large dataset (200 items)
- Using `ArrayPagination` to slice it (15 items/page)
- Adding only the current page's items to the grid
- Configuring pagination display options (adjacent count, edge count, page jump)
- Rendering with Bootstrap 5 pagination component (`<ul class="pagination">`)

---

## Rationale

- **Auto-invoke callbacks**: Simplifies the developer experience — register a callback and forget about the submission handling plumbing. The grid handles `$_POST` parsing and dispatching internally.
- **`PaginationInterface` (lean, 4 methods)**: Keeps the contract minimal so any storage paradigm (ORM, raw SQL, API, array) can implement it with minimal effort. Derived values (total pages, page ranges) are computed by `GridPagination`.
- **`ArrayPagination` bundled implementation**: Provides an immediately usable implementation for simple use cases and serves as the example showcase, as requested.
- **App-controlled URLs via `getPageURL(int)`**: Gives full flexibility for routing systems (query params, path segments, SEO URLs). The grid never makes assumptions about URL structure.
- **Jump-to-page via URL template derivation**: Avoids adding complexity to the interface. The sentinel-replacement trick (`999999999 → {PAGE}`) is robust and transparent.
- **Ellipsis-based page list**: Standard UX pattern for large datasets. Prevents rendering hundreds of page links.
- **Selection column as a pseudo-column**: Not added to `ColumnManager` — it's an implicit column injected by the renderer when actions are defined. This avoids polluting the user-defined column structure.

---

## Detailed Steps

### Phase 1: Complete Grid Actions

1. **`RegularAction` — add callback support**
   - Add `private ?callable $callback = null;`
   - Add `setCallback(callable $callback): self`
   - Add `getCallback(): ?callable`
   - Add `hasCallback(): bool`

2. **`GridActions` — expose value column + add processing**
   - Add `getValueColumn(): ?GridColumnInterface` getter
   - Add `getFormActionFieldName(): string` — returns `'grid_action'` (centralizes the field name)
   - Add `getFormSelectionFieldName(): string` — returns `'selected'` (centralizes the field name)
   - Implement `processSubmittedActions(): bool`:
     - Read `$_POST[$this->getFormActionFieldName()]` and `$_POST[$this->getFormSelectionFieldName()]`
     - Find matching `RegularAction` by name
     - If found and has callback → invoke callback with selected values array
     - Return `true` if processed, `false` otherwise
   - Fix or remove `render()` stub (rendering goes through the renderer, so either make it delegate to the active renderer, or remove it and update the manifest stub inventory)

3. **`StandardRow` — implement selection**
   - Add `getSelectValue(): string` — gets value from the configured value column:
     ```php
     public function getSelectValue(): string {
         $column = $this->getGrid()->actions()->getValueColumn();
         return (string)$this->getValue($column);
     }
     ```
   - Implement `getSelectionCell(): ?SelectionCell`:
     ```php
     private ?SelectionCell $selectionCell = null;
     
     public function getSelectionCell(): ?SelectionCell {
         if (!$this->isSelectable()) return null;
         if ($this->selectionCell === null) {
             $this->selectionCell = new SelectionCell($this, /* needs a column */);
         }
         return $this->selectionCell;
     }
     ```
   - **Design note on `SelectionCell` constructor**: `BaseCell` requires a `StandardRow` and a `GridColumnInterface`. The selection cell doesn't belong to a real column. Options:
     - (a) Create a dummy/virtual column for the selection (adds complexity).
     - (b) Refactor `SelectionCell` to not extend `BaseCell` — it only needs the row reference, not a column.
     - **Recommended: (b)** — Make `SelectionCell` a standalone class implementing `GridCellInterface` partially, or give it its own simpler base. It only needs the row. Currently `SelectionCell extends BaseCell` and `BaseCell` requires a column, but `SelectionCell` never uses `getColumn()`. Refactor `SelectionCell` to take only a `StandardRow` in its constructor and not extend `BaseCell`.

4. **`BaseGridRenderer` — render selection cells**
   - In `renderStandardRowCells()`: if `$row->isSelectable()`, render the selection cell's `<td>` before iterating regular columns.
   - Add `renderSelectionCell(SelectionCell $cell): string|StringableInterface` — wraps the checkbox in a `<td>`.
   - Add `createSelectionCell(SelectionCell $cell): HTMLTag` — protected helper.
   - In `renderHeaderCells()`: if actions are enabled, prepend a "select all" checkbox `<th>`.
   - Add `renderSelectionHeaderCell(): string|StringableInterface` — renders the "select all" `<th>` with a checkbox (`<input type="checkbox">` + minimal JS `onclick` to toggle all checkboxes).
   - Add `getColspan(): int` — returns `countColumns()` + 1 if actions are enabled, or `countColumns()` otherwise. Use this everywhere colspan is calculated (actions row, merged rows, empty message row).
   - Update `renderActionsRow()`:
     - Add `name` attribute to `<select>`: `->attr('name', $actions->getFormActionFieldName())`
     - Add a default empty `<option>` as the first option (placeholder: "Select action...")
     - Add a submit `<button>` next to the `<select>`
     - Update colspan to use `getColspan()`
   - Update `renderMergedRow()` call sites to pass `getColspan()` instead of `countColumns()`.

5. **`GridRendererInterface` — add selection methods to contract**
   - Add `renderSelectionCell(SelectionCell $cell): string|StringableInterface`
   - Add `renderSelectionHeaderCell(): string|StringableInterface`

6. **`DataGrid::generateOutput()` — integrate action processing**
   - At the very top of `generateOutput()`, call `$this->processActions()` which delegates to `GridActions::processSubmittedActions()` when actions are configured.

7. **`DataGridInterface` — add `actions()` to the interface**
   - Currently `actions()` is only on `DataGrid`, not on the interface. Add it so renderers can access it via the interface.

8. **Update Example `examples/3-grid-actions.php`**

   Rewrite the existing example into a fully working end-to-end demo of grid actions.

   **Structure:**
   ```
   ┌─ PHP block (before HTML) ─────────────────────────────────┐
   │  1. Create the grid + Bootstrap 5 renderer                │
   │  2. Define columns: ID (compact, right-aligned), Label,   │
   │     Status                                                │
   │  3. Set the value column to 'id'                          │
   │  4. Register actions:                                     │
   │     - 'delete' → "Delete selected" with callback           │
   │     - separator                                           │
   │     - 'archive' → "Archive selected" with callback         │
   │  5. Each callback stores a message in a $feedback variable │
   │     (e.g. "Deleted items: 1, 5, 12")                      │
   │  6. Add ~10 rows of sample data                           │
   └───────────────────────────────────────────────────────────┘
   ┌─ HTML output ─────────────────────────────────────────────┐
   │  - Bootstrap 5 CSS link                                   │
   │  - <h1>Grid Actions Example</h1>                          │
   │  - If $feedback is set: <div class="alert alert-success"> │
   │    showing the processed action result                    │
   │  - echo $grid (renders form + table + checkboxes +        │
   │    action dropdown + submit)                              │
   └───────────────────────────────────────────────────────────┘
   ```

   **Validates:**
   - Row selection checkboxes appear for each data row
   - "Select all" checkbox appears in the header
   - Action dropdown + submit button in the footer
   - Submitting the form calls the callback and displays feedback
   - Colspan correctly accounts for the selection column

### Phase 2: Pagination

9. **Create `PaginationInterface`** — `src/Grids/Pagination/PaginationInterface.php`
   ```php
   interface PaginationInterface {
       public function getTotalItems(): int;
       public function getItemsPerPage(): int;
       public function getCurrentPage(): int;
       public function getPageURL(int $page): string;
   }
   ```

10. **Create `GridPagination`** — `src/Grids/Pagination/GridPagination.php`
    - Constructor: `__construct(DataGrid $grid)`
    - `setProvider(PaginationInterface $provider): self`
    - `getProvider(): PaginationInterface`
    - `hasProvider(): bool`
    - `getTotalPages(): int` — `(int)ceil(totalItems / itemsPerPage)`
    - `getCurrentPage(): int` — delegates to provider, clamped to valid range
    - `getPageNumbers(int $adjacentCount = 2, int $edgeCount = 2): array` — returns array of `int|null` (null = ellipsis). Algorithm:
      - Always include pages `1..edgeCount` and `(total-edgeCount+1)..total`
      - Always include `(current-adjacentCount)..(current+adjacentCount)`
      - Insert `null` for any gap > 1 between segments
      - Deduplicate and sort
    - `getPageURLTemplate(): string` — derives from `getPageURL(999999999)` with sentinel replacement
    - `hasPreviousPage(): bool`
    - `hasNextPage(): bool`
    - `getPreviousPageURL(): string`
    - `getNextPageURL(): string`
    - `isPageJumpEnabled(): bool` / `setPageJumpEnabled(bool): self` — default `true`
    - `setAdjacentCount(int): self` — how many pages to show around current
    - `setEdgeCount(int): self` — how many pages to show at start/end

11. **Create `ArrayPagination`** — `src/Grids/Pagination/Types/ArrayPagination.php`
    - Constructor: `__construct(array $items, int $itemsPerPage = 25, ?int $currentPage = null, string $pageParam = 'page')`
    - If `$currentPage` is `null`, reads from `$_GET[$pageParam]` (defaults to `1`)
    - `getSlicedItems(): array` — returns `array_slice($items, offset, itemsPerPage)`
    - Implements `PaginationInterface`:
      - `getTotalItems()` → `count($items)`
      - `getItemsPerPage()` → stored value
      - `getCurrentPage()` → resolved current page (clamped to valid range)
      - `getPageURL(int $page)` → takes current request URI, replaces/adds the page query param
    - Helper: `getPageParameterName(): string`
    - Helper: builds base URL from `$_SERVER['REQUEST_URI']` by parsing query string, removing the page param, and reconstructing

12. **`GridRendererInterface` — add pagination method**
    - Add `renderPaginationRow(GridPagination $pagination): string|StringableInterface`

13. **`BaseGridRenderer` — implement default pagination rendering**
    - `renderPaginationRow(GridPagination $pagination)`:
      - Returns empty string if provider not set or total pages ≤ 1
      - Renders a `<tr><td colspan="...">` containing:
        - `<nav>` wrapper
        - Page link list (prev, numbered pages with ellipsis, next)
        - Jump-to-page input (if enabled)
      - Uses `getColspan()` for the colspan value
    - Protected helpers:
      - `createPaginationRow(GridPagination): HTMLTag`
      - `createPageLink(int $page, string $url, bool $isCurrent): HTMLTag` — `<a>` or `<span>` for current
      - `createEllipsis(): HTMLTag` — `<span class="pagination-ellipsis">…</span>`
      - `createPageJumpInput(GridPagination): HTMLTag` — number input + go button with JS
      - `createPreviousLink(GridPagination): HTMLTag`
      - `createNextLink(GridPagination): HTMLTag`

14. **`Bootstrap5Renderer` — override pagination for Bootstrap styling**
    - Override `renderPaginationRow()` to use Bootstrap 5 pagination markup:
      ```html
      <nav><ul class="pagination">
        <li class="page-item"><a class="page-link" href="...">1</a></li>
        <li class="page-item active"><span class="page-link">5</span></li>
        <li class="page-item disabled"><span class="page-link">…</span></li>
        ...
      </ul></nav>
      ```
    - Override the protected helpers to produce Bootstrap-compatible markup

15. **`DataGrid` — integrate pagination**
    - Add `private ?GridPagination $pagination = null;`
    - Add `pagination(): GridPagination` — lazy creates `GridPagination($this)`
    - In `generateOutput()`, after actions row and before footer bottom:
      ```php
      if ($this->pagination !== null && $this->pagination->hasProvider()) {
          echo $renderer->renderPaginationRow($this->pagination);
      }
      ```

16. **`DataGridInterface` — add `pagination()` to the interface**

17. **Create example `examples/4-pagination.php`** (NEW)

    A full working example showcasing pagination with `ArrayPagination`.

    **Structure:**
    ```
    ┌─ PHP block (before HTML) ─────────────────────────────────┐
    │  1. Generate a large dataset: 200 items                   │
    │     (loop creating arrays with 'id', 'title', 'category', │
    │      'status' keys — e.g. "Item #42", cycling categories) │
    │  2. Create ArrayPagination: 15 items/page, auto-detect    │
    │     current page from $_GET['page']                       │
    │  3. Create the grid + Bootstrap 5 renderer (bordered,     │
    │     hover, striped)                                       │
    │  4. Define columns: ID (compact, integer), Title (50%),   │
    │     Category, Status                                      │
    │  5. Set the pagination provider:                          │
    │     $grid->pagination()->setProvider($pagination)         │
    │  6. Add ONLY the current page's items to the grid:        │
    │     $grid->rows()->addArrays($pagination->getSlicedItems())│
    │  7. Optionally configure: adjacent count, edge count,     │
    │     page jump enabled                                     │
    └───────────────────────────────────────────────────────────┘
    ┌─ HTML output ─────────────────────────────────────────────┐
    │  - Bootstrap 5 CSS link                                   │
    │  - <h1>Pagination Example</h1>                            │
    │  - <p>Showing page X of Y (Z total items)</p>            │
    │  - echo $grid (renders table + footer with pagination     │
    │    controls: prev/next, numbered pages with ellipsis,     │
    │    jump-to-page input)                                    │
    └───────────────────────────────────────────────────────────┘
    ```

    **Validates:**
    - Pagination controls render in the footer with Bootstrap 5 styling
    - Page links work (clicking navigates via `?page=N`)
    - Ellipsis appears for large page counts
    - Current page is highlighted (active state)
    - Previous/Next are disabled at boundaries
    - Jump-to-page input navigates correctly
    - Only 15 items display per page, not the full 200
    - First/last pages show correct item counts

### Phase 3: Housekeeping

18. **Run `composer dump-autoload`** — register all new classes in the classmap.

19. **Run `composer analyze`** — fix any PHPStan level 6 issues in new/modified code.

20. **Update manifest documents:**
    - `api-surface.md` — add new classes, updated signatures
    - `file-tree.md` — add `Pagination/` directory and new files
    - `data-flows.md` — add pagination data flow, update actions data flow
    - `constraints.md` — remove completed stubs from inventory, add any new stubs
    - `tech-stack.md` — no changes (no new dependencies)

---

## Dependencies

- Steps 1–8 (Grid Actions) are independent of Steps 9–17 (Pagination).
- Within Grid Actions: Steps 1–3 (data layer) must precede Steps 4–5 (renderer) which must precede Step 6–8 (integration + example).
- Within Pagination: Step 9 (interface) → Step 10 (GridPagination) → Step 11 (ArrayPagination) → Steps 12–14 (renderer) → Steps 15–17 (integration + example).
- Step 18–20 (housekeeping) depends on all prior steps.

---

## Required Components

### Modified Files

| File | Feature |
|---|---|
| `src/Grids/Actions/Type/RegularAction.php` | Actions |
| `src/Grids/Actions/GridActions.php` | Actions |
| `src/Grids/Rows/Types/StandardRow.php` | Actions |
| `src/Grids/Cells/SelectionCell.php` | Actions (constructor refactor) |
| `src/Grids/Renderer/BaseGridRenderer.php` | Actions + Pagination |
| `src/Grids/Renderer/GridRendererInterface.php` | Actions + Pagination |
| `src/Grids/Renderer/Types/Bootstrap5Renderer.php` | Actions + Pagination |
| `src/Grids/Renderer/Types/DefaultRenderer.php` | Actions (if `renderCustomRow` changes needed) |
| `src/Grids/DataGrid.php` | Actions + Pagination |
| `src/Grids/DataGridInterface.php` | Actions + Pagination |
| `examples/3-grid-actions.php` | Actions |

### New Files

| File | Feature |
|---|---|
| `src/Grids/Pagination/PaginationInterface.php` | Pagination |
| `src/Grids/Pagination/GridPagination.php` | Pagination |
| `src/Grids/Pagination/Types/ArrayPagination.php` | Pagination |
| `examples/4-pagination.php` | Pagination |

### Manifest Files to Update

| File | Reason |
|---|---|
| `docs/agents/project-manifest/api-surface.md` | New classes + updated signatures |
| `docs/agents/project-manifest/file-tree.md` | New `Pagination/` directory + files |
| `docs/agents/project-manifest/data-flows.md` | New pagination flow + updated action flow |
| `docs/agents/project-manifest/constraints.md` | Remove completed stubs, document any new stubs |

---

## Assumptions

- The grid's `<form>` wrapping is always present when actions are used (it already is).
- Each grid generates its own `<form>`, so `$_POST` field names (`selected[]`, `grid_action`) are unambiguous per form submission (no multiple-grid-per-form scenario).
- `$_POST` superglobal is available for action processing (standard PHP web request).
- `$_GET` and `$_SERVER['REQUEST_URI']` are available for `ArrayPagination`'s URL building.
- The namespace anomaly in `StandardRow` (`WebcomicsBuilder\Grids\Rows\Types`) is not fixed as part of this plan (per constraints.md: do not fix unless explicitly asked).

---

## Constraints

- All new files must include `declare(strict_types=1);`.
- All setters must return `self`/`$this` for fluent chaining.
- All HTML output must use `HTMLTag` from `application-utils-core`.
- New traits must have matching interfaces.
- `composer dump-autoload` must be run after adding new files (classmap autoloading).
- PHPStan level 6 compliance required for all new/modified code.
- Do not fix the `WebcomicsBuilder` namespace anomaly.

---

## Out of Scope

- **Sorting implementation** — `getSortColumn()`, `getSortDir()`, `useCallbackSorting()`, `useManualSorting()` stubs remain as-is.
- **AJAX/JavaScript pagination** — pagination uses server-side page loads via links/form. No JS framework integration.
- **Pagination data caching** — the provider is called on every render; caching is the app's responsibility.
- **Unit tests** — no tests exist yet; this plan does not add them (but the testing strategy below covers how they should be structured).
- **Namespace anomaly fix** — `StandardRow`'s `WebcomicsBuilder` namespace is left as-is per constraints.
- **Custom pagination renderers** — only Default and Bootstrap 5 renderers are updated. Custom renderers can override the new methods.

---

## Acceptance Criteria

### Grid Actions
- [ ] Checkboxes appear in each data row when actions are configured.
- [ ] A "select all" checkbox appears in the header row.
- [ ] The footer contains an action dropdown with a submit button.
- [ ] Selecting items and choosing an action submits the form via POST.
- [ ] The registered PHP callback is invoked with an array of selected row primary key values.
- [ ] `GridActions::processSubmittedActions()` returns `true` when an action was processed, `false` otherwise.
- [ ] Colspan values correctly account for the selection column (+1) in merged rows, empty message rows, and the actions row.
- [ ] The `examples/3-grid-actions.php` example works end-to-end in a browser.
- [ ] PHPStan level 6 passes on all modified/new files.

### Pagination
- [ ] Pagination controls render in the grid footer when a provider is set.
- [ ] Page numbers are optimized for large sets (ellipsis-based compact display).
- [ ] Current page is visually distinguished (active state).
- [ ] Previous/Next navigation links are present and disabled at boundaries.
- [ ] Jump-to-page input accepts a page number and navigates to it.
- [ ] `ArrayPagination` correctly slices an array and generates valid URLs.
- [ ] Pagination does not render when total pages ≤ 1.
- [ ] Bootstrap 5 renderer produces proper Bootstrap pagination markup.
- [ ] The `examples/4-pagination.php` example works end-to-end in a browser.
- [ ] PHPStan level 6 passes on all modified/new files.

---

## Testing Strategy

While no tests are written as part of this plan, the following test structure is recommended for future implementation:

### Unit Tests (PHPUnit)

| Test Class | Coverage |
|---|---|
| `GridActionsTest` | `processSubmittedActions()` with mocked `$_POST`, callback invocation, value column resolution |
| `RegularActionTest` | Callback registration and retrieval |
| `StandardRowTest` | `getSelectValue()`, `getSelectionCell()`, `isSelectable()` |
| `SelectionCellTest` | `renderContent()` output includes correct checkbox markup |
| `GridPaginationTest` | `getTotalPages()`, `getPageNumbers()` algorithm with various edge cases (1 page, 2 pages, 100 pages, current at start/middle/end), `getPageURLTemplate()` |
| `ArrayPaginationTest` | `getSlicedItems()` correctness, `getPageURL()` format, boundary handling (first/last page, out-of-range) |
| `BaseGridRendererTest` | `getColspan()` with/without actions, `renderPaginationRow()` output structure |

### Integration Tests

| Test | Description |
|---|---|
| Full render with actions | Create grid with actions → render → verify HTML contains checkboxes, select dropdown, submit button |
| Full render with pagination | Create grid with ArrayPagination → render → verify HTML contains page links, jump input |
| Action callback execution | Simulate POST → render grid → verify callback was invoked with correct values |

---

## Risks & Mitigations

| Risk | Mitigation |
|---|---|
| **`$_POST` dependency makes unit testing harder** | `GridActions::processSubmittedActions()` should accept optional `$postData` parameter (defaults to `$_POST`) for testability. |
| **`SelectionCell` refactoring (removing `BaseCell` dependency) may break existing code** | `SelectionCell` is currently non-functional (calls missing method), so the refactor is safe. Ensure `GridCellInterface` compatibility is maintained. |
| **Sentinel value `999999999` in URL template derivation could collide** | Use a more unique sentinel like `__PAGE_PLACEHOLDER_999__` and document the assumption. Collision probability is negligible for real URLs. |
| **`ArrayPagination` relies on `$_GET` and `$_SERVER`** | Document that `ArrayPagination` is designed for standard HTTP requests. For CLI/testing, users must pass `$currentPage` explicitly. |
| **Colspan calculation scattered across renderer** | Centralizing in `getColspan()` method ensures consistency. All existing colspan calculations must be audited and updated. |
| **Bootstrap 5 pagination markup version sensitivity** | Pin to Bootstrap 5.3 component markup. The `twbs/bootstrap` dependency is already `^5.3.3`. |
