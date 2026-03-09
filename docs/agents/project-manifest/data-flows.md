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
  │      renderer->renderHeaderRow(HeaderRow, cols)  → <tr><th>...</th></tr>
  │      renderer->renderHeaderBottom(GridHeader)    → </thead>
  │
  ├─ 8. Render footer:
  │      renderer->renderFooterTop(GridFooter)       → <tfoot>
  │      │
  │      ├─ If repeat header enabled & threshold met:
  │      │   renderer->renderHeaderRowRepeated(HeaderRow, cols)
  │      │
  │      ├─ If actions defined:
  │      │   renderer->renderActionsRow(GridActions)
  │      │   └─ <tr><td colspan="..."><select>
  │      │        <option>action</option>
  │      │        <option class="separator">-----</option>
  │      │      </select></td></tr>
  │      │
  │      └─ renderer->renderFooterBottom(GridFooter) → </tfoot>
  │
  ├─ 9. Render body:
  │      renderer->renderBody(rows, cols)            → <tbody>
  │      │
  │      ├─ For each row:
  │      │   ├─ MergedRow  → renderMergedRow()
  │      │   │   └─ <tr><td colspan="...">content</td></tr>
  │      │   │
  │      │   ├─ StandardRow → renderStandardRow()
  │      │   │   └─ <tr>
  │      │   │        For each column:
  │      │   │          StandardRow::getCell(column) → RegularCell
  │      │   │          RegularCell::renderContent()
  │      │   │            └─ column.formatValue(cell.value) → string
  │      │   │          renderer->renderRowCell(cell) → <td>...</td>
  │      │   │      </tr>
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
    StandardRow::getSelectionCell() → SelectionCell
      └─ renders <input type="checkbox" name="selected[]" value="...">

  Action dropdown rendered in footer via renderActionsRow()
```

## 5. Renderer Selection

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
