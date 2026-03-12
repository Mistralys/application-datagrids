# Key Data Flows

## 1. Grid Creation & Configuration

```
User code
  │
  ├─ DataGrid::create(?id)
  │    └─ constructor initializes:
  │         ColumnManager, RowManager, GridOptions,
  │         GridHeader, GridFooter, GridForm, RendererManager
  │
  ├─ $grid->columns()->add('name', 'Label')     → DefaultColumn
  │   $grid->columns()->addInteger('id', 'ID')  → IntegerColumn
  │   (columns stored in ColumnManager::$columns)
  │
  ├─ $grid->rows()->addArray([...])              → StandardRow
  │   $grid->rows()->addArrays([[...], [...]])   → multiple StandardRows
  │   $grid->rows()->addMerged('text')           → MergedRow
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
  │             └─ For each column: renderHeaderCell(col) → <th>...</th>
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
  //     <nav>
  //       <span class="disabled">Previous</span> | <a href="...">Previous</a>
  //       <span class="current-page">{n}</span> | <a href="...">{n}</a>
  //       <span class="pagination-ellipsis">…</span>  ← null sentinel
  //       <span class="disabled">Next</span> | <a href="...">Next</a>
  //       [<span><input type=number><button onclick="...">Go</button></span>] ← if jump enabled
  //     </nav>
  //   </td></tr>

  // Bootstrap5Renderer output:
  //   <tr><td colspan="{colspan}">
  //     <nav aria-label="Page navigation">
  //       <ul class="pagination">
  //         <li class="page-item [disabled]"><[span|a] class="page-link" aria-label="Previous page">&laquo;</[span|a]></li>
  //         <li class="page-item [active]" [aria-current="page"]><[span|a] class="page-link">{n}</[span|a]></li>
  //         <li class="page-item disabled"><span class="page-link">&hellip;</span></li>  ← ellipsis
  //         <li class="page-item [disabled]"><[span|a] class="page-link" aria-label="Next page">&raquo;</[span|a]></li>
  //       </ul>
  //       [<div class="d-flex align-items-center gap-2 mt-2">
  //         <input class="form-control form-control-sm" style="width:80px"><button class="btn btn-sm btn-outline-secondary">Go</button>
  //       </div>]  ← if jump enabled
  //     </nav>
  //   </td></tr>

  // NOTE: DataGrid::generateOutput() calls renderPaginationRow() inside the footer
  // when $this->pagination !== null && $this->pagination->hasProvider().

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
