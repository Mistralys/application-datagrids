# Key Data Flows

## 1. Grid Creation & Configuration

```
User code
  │
  ├─ DataGrid::create(string $id, GridStorageInterface $storage)
  │    └─ constructor initializes:
  │         $storage (required injection), $settings (null — lazy via settings()),
  │         ColumnManager, RowManager, GridOptions,
  │         GridHeader, GridFooter, GridForm, RendererManager
  │    └─ throws DataGridException::ERROR_INVALID_GRID_ID (260301)
  │         when $id does not match /^[a-z][a-z0-9]*(-[a-z0-9]+)*$/
  │
  ├─ $grid->columns()->add('name', 'Label')     → DefaultColumn
  │   $grid->columns()->addInteger('id', 'ID')  → IntegerColumn
  │   (columns stored in ColumnManager::$columns)
  │
  ├─ $grid->rows()->addArray([...])              → StandardRow
  │   $grid->rows()->addArrays([[...], [...]])   → multiple StandardRows
  │   $grid->rows()->addMerged('text')           → MergedRow
  │   (all calls go through RowManager::registerRow(), which calls
  │    BaseGridRow::setRowManager($this) on the row before storing it;
  │    this back-reference lets rows call getGrid() at render time)
  │   (rows stored in RowManager::$rows)
  │
  ├─ $grid->options()->setEmptyMessage(...)
  │   $grid->options()->setRepeatHeader(true, 10)
  │
  └─ $grid->renderer()->selectBootstrap5()       → Bootstrap5Renderer
      ->makeStriped()->makeBordered()
```

## 2. Rendering Pipeline

When `$grid->render()` or `echo $grid` is called:

```
DataGrid::generateOutput()
  │
  ├─ 0. Process submitted actions (before any output, idempotent):
  │      If not already processed via $grid->processActions():
  │        If $this->actions !== null:
  │          $this->actions->processSubmittedActions()
  │          (reads $_POST, executes matching callback; no-op if no POST data)
  │
  ├─ 1. Get renderer:  RendererManager::getRenderer()
  │      (defaults to DefaultRenderer if none selected)
  │
  ├─ 2. Resolve rows:  DataGrid::resolveRows()
  │      └─ If no rows exist → creates MergedRow with empty message
  │
  ├─ 2b. Sort rows (if sortManager initialized):
  │      If isset($this->sortManager):
  │        $this->sortManager->sortRows($rows)   ← dispatched via SortManagerInterface
  │        └─ No-op if no sort column is active (getSortColumn() returns null)
  │        └─ No-op for Manual mode (consumer controls ordering)
  │        └─ Partitions StandardRows from non-standard rows, sorts StandardRows
  │           in-place (Native or Callback mode), restores them to their original
  │           relative positions; MergedRow slots remain unchanged.
  │
  ├─ 3. Get header row:  RowManager::getHeaderRow()
  │      └─ Creates HeaderRow if options()->isHeaderRowEnabled()
  │
  ├─ 4. Get columns:  ColumnManager::getColumns()
  │
  ├─ 5. Render form opening:
  │      renderer->renderGridFormTop(GridForm)
  │      └─ <form> with hidden variables as <input type="hidden">
  │
  ├─ 6. Render table opening:
  │      renderer->renderGridTop()
  │      └─ <div class="grid-container"><table id="..." class="...">
  │
  ├─ 7. Render header:
  │      renderer->renderHeaderTop(GridHeader)      → <thead>
  │      renderer->renderHeaderRow(HeaderRow, cols)
  │        └─ renderHeaderCells(cols)
  │             ├─ If grid->hasActions():
  │             │    renderSelectionHeaderCell() → <th><input type="checkbox" id="grid-{id}-select-all" onclick="..."/></th>
  │             └─ For each column: renderHeaderCell(col) → createHeaderCell(col)
  │                   ├─ col.isSortable() = false → <th> plain text label </th>
  │                   └─ col.isSortable() = true  → <th> <a href="getSortURL"> label [<span>▲|▼</span>] </a> </th>
  │                   (Bootstrap5Renderer overrides createSortAnchor(): same structure;
  │                    <a> gains Bootstrap utility classes: text-decoration-none text-reset
  │                    d-inline-flex align-items-center gap-1)
  │      renderer->renderHeaderBottom(GridHeader)    → </thead>
  │
  ├─ 8. Render footer:
  │      renderer->renderFooterTop(GridFooter)       → <tfoot>
  │      │
  │      ├─ If repeat header enabled & threshold met:
  │      │   renderer->renderHeaderRowRepeated(HeaderRow, cols)
  │      │
  │      ├─ If pagination set and has a provider:
  │      │   renderer->renderPaginationRow(GridPagination)
  │      │   └─ colspan = getColspan() = countColumns()+1 when actions exist
  │      │      (Bootstrap5Renderer renders a <ul class="pagination"> nav; DefaultRenderer renders plain links)
  │      │      Visibility: returns '' when no provider, or when getTotalPages() <= 1 AND !hasItemsPerPageOptions().
  │      │      When IPP options are configured the row always renders (even on 0-item/single-page grids).
  │      │
  │      ├─ If actions defined:
  │      │   renderer->renderActionsRow(GridActions)
  │      │   └─ colspan = getColspan() = countColumns()+1 when actions exist
  │      │      <tr><td colspan="{colspan}">
  │      │        <select name="{getFormActionFieldName()}">
  │      │          <option value="" disabled selected>Select action…</option>   ← placeholder (disabled, cannot be submitted)
  │      │          <option value="{name}">{label}</option>   ← RegularAction
  │      │          <option class="separator">-----</option>  ← SeparatorAction
  │      │        </select>
  │      │        <button type="submit">Apply</button>
  │      │      </td></tr>
  │      │
  │      └─ renderer->renderFooterBottom(GridFooter) → </tfoot>
  │
  ├─ 9. Render body:
  │      renderer->renderBody(rows, cols)            → <tbody>
  │      │
  │      ├─ For each row:
  │      │   ├─ MergedRow  → renderMergedRow(colspan=getColspan())
  │      │   │   └─ <tr><td colspan="{getColspan()}">content</td></tr>
  │      │   │
  │      │   ├─ StandardRow → renderStandardRow()
  │      │   │   └─ renderStandardRowCells(row, cols)
  │      │   │        ├─ If row->isSelectable():
  │      │   │        │    row->getSelectionCell() → SelectionCell
  │      │   │        │    renderer->renderSelectionCell(cell) → <td>...</td>
  │      │   │        └─ For each column:
  │      │   │             StandardRow::getCell(column) → RegularCell
  │      │   │             RegularCell::renderContent()
  │      │   │               └─ column.formatValue(cell.value) → string
  │      │   │             renderer->renderRowCell(cell) → <td>...</td>
  │      │   │
  │      │   └─ Other → renderCustomRow() (extensibility hook)
  │      │
  │      └─ </tbody>
  │
  ├─ 10. renderer->renderGridBottom()   → </table></div>
  │
  └─ 11. renderer->renderGridFormBottom(GridForm) → </form>
```

## 3. Cell Value Formatting

```
RegularCell::renderContent()
  └─ GridColumnInterface::formatValue(value)
       ├─ DefaultColumn: casts scalars/null to string
       └─ IntegerColumn: casts numeric values to int→string, non-numeric to ''
```

## 4. Row Selection (Actions)

```
User code:
  $grid->actions()->setValueColumn('id')
  $grid->actions()->add('delete', 'Delete')

During render:
  StandardRow::isSelectable()
    └─ checks GridActions::hasActions()

  If selectable:
    StandardRow::getSelectionCell() → SelectionCell (lazy-cached)
      │
      ├─ SelectionCell::renderContent()
      │    └─ StandardRow::getSelectValue()
      │         └─ GridActions::getValueColumn() → ?GridColumnInterface
      │              └─ StandardRow::getCell(valueColumn)->getValue() → string
      │    produces: <input type="checkbox" name="selected[]" value="{selectValue}"/>
      │
      └─ field name sourced from GridActions::getFormSelectionFieldName() → 'selected'

  Action dropdown rendered in footer via renderActionsRow()
    └─ <select name="grid_action">
         <option value="{name}">{label}</option>   ← RegularAction
         <option disabled class="separator">---</option>  ← SeparatorAction
       </select>
    └─ field name sourced from GridActions::getFormActionFieldName() → 'grid_action'
```

## 5. Pagination Usage

`DataGrid::pagination()` lazily creates and returns a `GridPagination` instance tied to the grid. Pagination automatically renders inside `echo $grid` when a provider is set.

```
User code:
  $provider = new ArrayPagination($allItems, 25);  // or custom PaginationInterface

  // Attach provider and slice items for the current page:
  $grid->pagination()->setProvider($provider);
  $grid->rows()->addArrays($provider->getSlicedItems());

  echo $grid;  // renderPaginationRow() is called automatically inside generateOutput()
  //   (renders nothing when hasProvider() is false or getTotalPages() <= 1)

  // renderPaginationRow is still callable directly if needed (e.g. outside the grid):
  // echo $grid->renderer()->getRenderer()->renderPaginationRow($grid->pagination());
  //   Returns '' when no provider or totalPages ≤ 1.

  // DefaultRenderer output (BaseGridRenderer):
  //   <tr><td colspan="{colspan}">
  //     [<nav>  ← only when getTotalPages() > 1
  //       <span class="disabled">Previous</span> | <a href="...">Previous</a>
  //       <span class="current-page">{n}</span> | <a href="...">{n}</a>
  //       <span class="pagination-ellipsis">…</span>  ← null sentinel
  //       <span class="disabled">Next</span> | <a href="...">Next</a>
  //       [<span><input type=number><button onclick="...">Go</button></span>] ← if jump enabled
  //     </nav>]
  //     [<select onchange="window.location.href = '...'.replace('{IPP}', this.value)">
  //       <option value="{n}" [selected]>{n} per page</option> ...
  //     </select>]  ← if hasItemsPerPageOptions()
  //   </td></tr>

  // Bootstrap5Renderer output:
  //   <tr><td colspan="{colspan}">
  //     [<nav aria-label="Page navigation">  ← only when getTotalPages() > 1
  //       <ul class="pagination">
  //         <li class="page-item [disabled]"><[span|a] class="page-link" aria-label="Previous page">&laquo;</[span|a]></li>
  //         <li class="page-item [active]" [aria-current="page"]><[span|a] class="page-link">{n}</[span|a]></li>
  //         <li class="page-item disabled"><span class="page-link">&hellip;</span></li>  ← ellipsis
  //         <li class="page-item [disabled]"><[span|a] class="page-link" aria-label="Next page">&raquo;</[span|a]></li>
  //       </ul>
  //       [<div class="d-flex align-items-center gap-2 mt-2">
  //         <input class="form-control form-control-sm" style="width:80px"><button class="btn btn-sm btn-outline-secondary">Go</button>
  //       </div>]  ← if jump enabled
  //     </nav>]
  //     [<div class="d-flex align-items-center gap-2 mt-2">
  //       <select class="form-select form-select-sm" style="width:auto" onchange="...">
  //         <option value="{n}" [selected]>{n} per page</option> ...
  //       </select>
  //     </div>]  ← if hasItemsPerPageOptions()
  //   </td></tr>

  // NOTE: DataGrid::generateOutput() calls renderPaginationRow() inside the footer
  // when $this->pagination !== null && $this->pagination->hasProvider().
  // The row is suppressed when getTotalPages() <= 1 AND !hasItemsPerPageOptions() (backward compat).

  // Computed properties still available directly:
  $totalPages  = $pagination->getTotalPages();
  $currentPage = $pagination->getCurrentPage();     // clamped to [1, totalPages]
  $pageNumbers = $pagination->getPageNumbers();
  //   → array<int|null>  (null = ellipsis sentinel)
  //   e.g. [1, 2, null, 5, 6, 7, null, 12, 13]

  // Prev / Next — always check guards before calling URL getters:
  if ($pagination->hasPreviousPage()) {
      $prevURL = $pagination->getPreviousPageURL();
  }
  if ($pagination->hasNextPage()) {
      $nextURL = $pagination->getNextPageURL();
  }

  // JavaScript-friendly URL template:
  $urlTemplate = $pagination->getPageURLTemplate();
  //   → "/path?page={PAGE}"  (internal 12-digit sentinel replaced with {PAGE})

  // ----- Items-per-page resolution (WP-002) -----
  // Configure once; resolveItemsPerPage() handles GET → GridSettings → default priority chain.
  // Only available when setItemsPerPageOptions() has been called with at least one option.
  $pagination->setItemsPerPageOptions([10, 25, 50, 100]);
  $itemsPerPage = $pagination->resolveItemsPerPage(25);  // or just resolveItemsPerPage() to use configured default
  //   Priority chain (first match wins):
  //   1. $_GET[$ippParam] — if present and in options whitelist → persisted to GridSettings → cached
  //   2. GridSettings::getItemsPerPage($fallback) — previously persisted value
  //   3. $default argument (or setDefaultItemsPerPage() value; built-in default: 25)
  //   Returns the same value on repeated calls (lazy cache for the lifetime of the object).
  $urlTemplate = $pagination->getItemsPerPageURLTemplate();
  //   → "/path?page=1&ipp={IPP}"  (page reset to 1; IPP_SENTINEL replaced with {IPP})
```

---

## 6. Form Submission Processing

`DataGrid::generateOutput()` automatically calls `processSubmittedActions()` when `$this->actions !== null` — **before** any HTML is emitted. This means you do **not** need to call it manually in the page handler; it runs as part of `echo $grid` / `render()`. If you need to inspect the result before rendering, call `$grid->processActions()` explicitly beforehand (it is idempotent — both the explicit call and the safety-net call in `generateOutput()` share a `$actionsProcessed` flag, so the action callback runs at most once per request).

Flow when called (automatically or manually):

```
$handled = $grid->actions()->processSubmittedActions($postData);
│
├─ $postData defaults to null → falls back to $_POST
│   (passing [] is treated as an empty array — no fallback)
│
│  Prefer calling $grid->processActions() instead for idempotent dispatch:
│
├─ Reads $postData[ getFormActionFieldName() ]  → action name string
│   (field: 'grid_action'; if absent → returns false immediately)
│
├─ Reads $postData[ getFormSelectionFieldName() ] → selected row values
│   (field: 'selected'; if absent → $selectedValues = [])
│
├─ Iterates registered actions:
│   ├─ SeparatorAction → skipped
│   └─ RegularAction   → compared by getName()
│        └─ If name matches:
│             ├─ getCallback() → callable|null
│             │    └─ If set: $callback($selectedValues)   ← receives string[]
│             └─ returns true
│
└─ No matching RegularAction found → returns false
```

---

## 7. Renderer Selection

```
$grid->renderer()                          → RendererManager
  ->selectBootstrap5()                     → Bootstrap5Renderer
     └─ init() adds 'table' CSS class
  ->makeStriped()                          → adds 'table-striped'
  ->makeBordered()                         → adds 'table-bordered'

// Or select by class:
$grid->renderer()->selectByClass(MyCustomRenderer::class)
  └─ Instantiates custom renderer with $grid, stores it

// Fallback:
RendererManager::getRenderer()
  └─ If none selected → selectDefault() → DefaultRenderer
```

---

## 8. Column Sorting

`DataGrid::sorting()` lazily creates and returns a `SortManagerInterface` instance. Sorting is
applied automatically during `echo $grid` / `render()` when a sort column is active.

### Configuration

```php
// Enable native sorting on a column:
$grid->columns()->add('name', 'Name')->useNativeSorting();
$grid->columns()->add('age', 'Age')->useNativeSorting();

// Or callback sorting (useful when cell values need custom comparison logic):
$grid->columns()->add('status', 'Status')
    ->useCallbackSorting(function (StandardRow $a, StandardRow $b, GridColumnInterface $col): int {
        return strcmp($a->getValue($col), $b->getValue($col));
    });

// Customize $_GET parameter names (defaults: 'sort', 'sort_dir'):
$grid->sorting()->setColumnParam('order_by')->setDirectionParam('dir');

// URL parameters are read lazily on first access to getSortColumn() / getSortDir().
// setColumnParam() / setDirectionParam() must be called BEFORE any read — they are
// silently ignored once resolveSortState() has run.
```

### Sort state resolution (lazy, on first `getSortColumn()` / `getSortDir()` call)

```
$_GET['sort']     → column name
  └─ ColumnManager::getByName(name)  — GridColumnException caught → no sort
  └─ column->isSortable()            — false → no sort

$_GET['sort_dir'] → direction string (case-insensitive)
  └─ 'ASC' or 'DESC' → stored as-is (uppercased)
  └─ any other value  → defaults to DataGridInterface::SORT_ASC

If column is invalid: $sortColumn = null, $sortDir = SORT_ASC (no sorting).
```

### URL building (`getSortURL()`)

```
$sorting->getSortURL($column)
  │
  ├─ Reads $_SERVER['REQUEST_URI']             e.g. '/products?page=2&sort=name'
  ├─ parse_url() → splits path from query string
  ├─ parse_str() → decodes existing query params
  ├─ Determines direction:
  │    If $column === current sort column → toggle (ASC→DESC, DESC→ASC)
  │    Else → ASC
  ├─ Sets $params[$columnParam] = $column->getName()
  │        $params[$dirParam]   = $direction
  │   (all existing params preserved — pagination param survives)
  └─ http_build_query() → reassembles query string
     Returns: '/products?page=2&sort=name&sort_dir=DESC'

  NOTE: returned URL is a raw string (not HTML-encoded).
  Callers must HTML-encode it before embedding in HTML attributes.
  The HTMLTag convention used by all renderers handles this automatically.
```

### Sort-aware header cell rendering (WP-003)

```
renderHeaderCell(GridColumnInterface $column)
  └─ createHeaderCell(GridColumnInterface $column)
       │
       ├─ col.isSortable() = false
       │    <th id col classes text-align> label text </th>
       │
       └─ col.isSortable() = true  →  createSortAnchor(col, [])
            <a href=getSortURL(col)>       ← URL toggles ASC↔DESC for active col, else ASC
              label
              [<span>▲</span>]              ← if isSortedBy(col) ∧ dir == ASC
              [<span>▼</span>]              ← if isSortedBy(col) ∧ dir == DESC
            </a>

       Wrapped in: <th id col classes text-align [nowrap] [compact-width]>

Bootstrap5Renderer override (Template Method):
  └─ createSortAnchor($column, $extraClasses)
       prepends Bootstrap utility classes before any caller-supplied $extraClasses:
         text-decoration-none  text-reset  d-inline-flex  align-items-center  gap-1
       delegates to parent::createSortAnchor($column, $mergedClasses)
       Non-sortable columns fall through to the inherited parent::createHeaderCell() unchanged.
```

### Row sorting in `generateOutput()`

```
DataGrid::generateOutput()
  │
  └─ After resolveRows():
       If isset($this->sortManager):          ← guard: only runs when sorting() was called
         $this->sortManager->sortRows($rows)  ← dispatched via SortManagerInterface
           │
           ├─ getSortColumn() → null          → return (no-op)
           ├─ getSortMode() → Manual          → return (no-op)
           │
           ├─ Partition rows:
           │    StandardRow[]  → $standardRows (with their original $rows indices)
           │    Other rows     → left in place (MergedRow, HeaderRow, etc.)
           │
           ├─ count($standardRows) < 2        → return (nothing to sort)
           │
           ├─ SortMode::Native:
           │    usort() with compareValues():
           │      Both numeric → spaceship <=> operator
           │      Both string  → strcasecmp()
           │      Mixed        → strcasecmp((string)$a, (string)$b)
           │    Result negated for DESC.
           │
           ├─ SortMode::Callback:
           │    usort() with $column->getSortCallback()($a, $b, $column)
           │    Result negated for DESC.
           │
           └─ Reassemble: $standardRows written back into their original indices
              Non-standard rows remain at their original positions in $rows.
```

---

## 9. Grid Settings (Per-Grid Persistence)

`DataGrid::settings()` lazily creates and returns a `GridSettings` instance tied to the grid's ID and storage handler.

### Reading a setting (with fallback)

```
$grid->settings()
  │
  └─ DataGrid::settings()
       └─ Lazy-creates GridSettings($this->id, $this->storage) on first call
            └─ Subsequent calls return the same instance ($this->settings property)

$grid->settings()->getItemsPerPage(?int $default = null): ?int
  │
  └─ GridSettings::getItemsPerPage($default)
       └─ GridStorageInterface::get($gridID, 'items_per_page', $default)
            ├─ JsonFileStorage: reads {storagePath}/{gridID}.json (memory-cached per request)
            │    └─ Returns $data['items_per_page'] if present
            │    └─ Returns $default if key absent or file not yet created
            └─ Custom implementation: any lookup logic
```

### Writing a setting (fluent)

```
$grid->settings()->setItemsPerPage(int $value): self
  │
  └─ GridSettings::setItemsPerPage($value)
       └─ GridStorageInterface::set($gridID, 'items_per_page', $value)
            └─ JsonFileStorage: reads existing {gridID}.json → merges new key/value
                 → json_encode → file_put_contents with LOCK_EX
                 → updates in-memory cache ($this->cache[$gridID])
```

### Typical pagination integration pattern

```
// Page render:

$itemsPerPage = $grid->settings()->getItemsPerPage(25);  // 25 = application default
//   → reads storage; returns stored int or 25 if nothing persisted yet

$provider = new ArrayPagination($allItems, $itemsPerPage);
$grid->pagination()->setProvider($provider);
$grid->rows()->addArrays($provider->getSlicedItems());

echo $grid;

// Optional — persist user-selected page size (e.g. from a submitted form):
if (isset($_POST['items_per_page'])) {
    $grid->settings()->setItemsPerPage((int)$_POST['items_per_page']);
}
```

### Isolation guarantee

Each `GridSettings` instance is scoped to one grid ID. Two grids on the same page sharing the same `JsonFileStorage` instance write to separate JSON files (`grid-a.json`, `grid-b.json`) and never interfere with each other.
