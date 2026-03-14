# Public API Surface

Signatures only — no implementation logic. Grouped by namespace/module.

---

## Core

### `DataGridInterface` (interface)

```php
interface DataGridInterface extends RenderableInterface, ClassableInterface
{
    public const SORT_ASC = 'ASC';
    public const SORT_DESC = 'DESC';

    public function getID(): string;
    public function options(): GridOptions;
    public function columns(): ColumnManager;
    public function rows(): RowManager;
    public function actions(): GridActions;
    public function hasActions(): bool;           // no lazy init — false when no actions configured
    public function processActions(): bool;       // idempotent — dispatches action callback at most once per request
    public function pagination(): GridPagination;
    public function sorting(): SortManagerInterface;
    public function getSortColumn(): ?GridColumnInterface;
    public function getSortDir(): string;
}
```

### `DataGrid`

```php
class DataGrid implements DataGridInterface
{
    // Traits: RenderableBufferedTrait, ClassableTrait

    public function __construct(?string $id = null);
    public static function create(?string $id = null): static;
    public function getID(): string;
    public function options(): GridOptions;
    public function columns(): ColumnManager;
    public function rows(): RowManager;
    public function footer(): GridFooter;
    public function header(): GridHeader;
    public function form(): GridForm;
    public function renderer(): RendererManager;
    public function actions(): GridActions;
    public function processActions(): bool;       // idempotent — dispatches action callback at most once per request
    public function pagination(): GridPagination;
    public function sorting(): SortManagerInterface;
    public function getSortColumn(): ?GridColumnInterface;
    public function getSortDir(): string;
}
```

### `DataGridException`

```php
class DataGridException extends BaseException
{
    public const ERROR_NO_PAGINATION_PROVIDER = 171700;
    public const ERROR_NO_VALUE_COLUMN = 171701;
    public const ERROR_NO_ROW_MANAGER = 171702;
}
```

---

## Columns (`Columns/`)

### `SortMode` (enum)

```php
enum SortMode: string   // backed string enum
{
    case Native   = 'native';
    case Callback = 'callback';
    case Manual   = 'manual';
}
```

### `GridColumnInterface` (interface)

```php
interface GridColumnInterface extends ClassableInterface, IDInterface, AlignInterface
{
    public function getName(): string;
    public function getLabel(): string;
    public function setNowrap(bool $nowrap = true): self;
    public function isNowrap(): bool;
    public function setCompact(bool $compact = true): self;
    public function isCompact(): bool;
    public function useNativeSorting(): self;
    public function useManualSorting(): self;
    public function useCallbackSorting(callable $callback): self;
    public function isSortable(): bool;
    public function getSortMode(): ?SortMode;
    public function getSortCallback(): ?\Closure;
    public function setWidth(int|string|NumberInfo|NULL $width): self;
    public function getWidth(): ?NumberInfo;
    public function formatValue(mixed $value): string;
}
```

### `BaseGridColumn` (abstract)

```php
abstract class BaseGridColumn implements GridColumnInterface
{
    // Traits: IDTrait, ClassableTrait, AlignTrait

    public function __construct(string $name, string|StringableInterface|NULL $label);
    abstract protected function init(): void;
    public function getName(): string;
    public function getLabel(): string;
    public function setNowrap(bool $nowrap = true): self;
    public function isNowrap(): bool;
    public function setCompact(bool $compact = true): self;
    public function isCompact(): bool;
    public function useNativeSorting(): self;
    public function useCallbackSorting(callable $callback): self;
    public function useManualSorting(): self;
    public function isSortable(): bool;
    public function getSortMode(): ?SortMode;
    public function getSortCallback(): ?\Closure;
    public function setWidth(int|string|NumberInfo|NULL $width): self;
    public function getWidth(): ?NumberInfo;
}
```

### `ColumnManager`

```php
class ColumnManager
{
    public function add(string $name, string|StringableInterface|NULL $label): DefaultColumn;
    public function addInteger(string $name, string|StringableInterface|NULL $label): IntegerColumn;
    public function register(GridColumnInterface $column): self;
    public function getColumns(): array; // GridColumnInterface[]
    public function getByName(string $name): GridColumnInterface;
    public function countColumns(): int;
}
```

### `GridColumnException`

```php
class GridColumnException extends DataGridException
{
    public const INVALID_ALIGN_VALUE = 171601;
    public const INVALID_COLUMN_NAME = 171602;
    public const COLUMN_NOT_FOUND_BY_NAME = 171603;
}
```

### Column Types

#### `DefaultColumn`

```php
class DefaultColumn extends BaseGridColumn
{
    protected function init(): void;
    public function formatValue(mixed $value): string;
}
```

#### `IntegerColumn`

```php
class IntegerColumn extends BaseGridColumn
{
    protected function init(): void;  // sets alignRight, nowrap, compact
    public function formatValue(mixed $value): string;
}
```

---

## Rows (`Rows/`)

### `GridRowInterface` (interface)

```php
interface GridRowInterface extends ClassableInterface, IDInterface
{
    public function getGrid(): DataGridInterface;
    public function isSelectable(): bool;
}
```

### `BaseGridRow` (abstract)

```php
abstract class BaseGridRow implements GridRowInterface
{
    // Traits: ClassableTrait, IDTrait

    public function setRowManager(RowManager $manager): self;
    public function getGrid(): DataGridInterface;    // throws DataGridException::ERROR_NO_ROW_MANAGER if manager not set
    public function isSelectable(): bool;            // false when manager not set; delegates to hasActions() otherwise
}
```

### `RowManager`

```php
class RowManager
{
    public function __construct(DataGridInterface $grid);
    public function getGrid(): DataGridInterface;
    public function addArrays(array $rows): self;               // @param array<int, array<string, mixed>> $rows
    public function addArray(array $columnValues): StandardRow; // @param array<string, mixed> $columnValues
    public function addMerged(string|StringableInterface|NULL $content = null): MergedRow;
    public function registerRow(GridRowInterface $row): self;
    public function getRows(): array; // GridRowInterface[]
    public function isHeaderRowEnabled(): bool;
    public function getHeaderRow(): ?HeaderRow;
}
```

### `GridRowException`

```php
class GridRowException extends DataGridException {}
```

### Row Types

#### `StandardRow`

```php
class StandardRow extends BaseGridRow
{
    public function __construct(RowManager $manager, array $values = array());
    public function setValue(GridColumnInterface|string $column, mixed $value): self;
    public function getCell(GridColumnInterface|string $column): RegularCell;
    public function getValue(GridColumnInterface|string $column): mixed;
    public function setValues(array $values): GridRowInterface;
    public function getSelectValue(): string;      // returns the string value of the value-column cell
    public function getSelectionCell(): ?SelectionCell;  // null when not selectable; throws DataGridException::ERROR_NO_VALUE_COLUMN when selectable but value column not configured
    // getGrid() and isSelectable() inherited from BaseGridRow
}
```

#### `MergedRow`

```php
class MergedRow extends BaseGridRow
{
    public function __construct(string|StringableInterface|NULL $content = null);
    public function isSelectable(): bool;    // always returns false
    public function setContent(string|StringableInterface|NULL $content): self;
    public function getContent(): string;
}
```

#### `HeaderRow`

```php
class HeaderRow extends BaseGridRow
{
    public function isSelectable(): bool;    // always returns false
    public function getRepeatedID(): ?string;
}
```

---

## Cells (`Cells/`)

### `GridCellInterface` (interface)

```php
interface GridCellInterface extends ClassableInterface
{
    public function getColumn(): GridColumnInterface;
    public function getRow(): StandardRow;
    public function getGrid(): DataGridInterface;
    public function renderContent(): string;
}
```

### `BaseCell` (abstract)

```php
abstract class BaseCell implements GridCellInterface
{
    // Traits: ClassableTrait

    public function __construct(StandardRow $row, GridColumnInterface $column);
    abstract protected function init(): void;
    public function getColumn(): GridColumnInterface;
    public function getRow(): StandardRow;
    public function getGrid(): DataGridInterface;
}
```

### `RegularCell`

```php
class RegularCell extends BaseCell implements IDInterface, AlignInterface
{
    // Traits: IDTrait, AlignTrait

    public function __construct(StandardRow $row, GridColumnInterface $column, mixed $value = null);
    public function resolveAlign(): ?string;
    public function setValue(mixed $value): self;
    public function getValue(): mixed;
    public function renderContent(): string;
}
```

### `SelectionCell`

Standalone class — does **not** extend `BaseCell` or implement `GridCellInterface` (has no associated column).
Accessed via `StandardRow::getSelectionCell()`.

```php
class SelectionCell
{
    public function __construct(StandardRow $row);
    public function getRow(): StandardRow;
    public function renderContent(): string;  // <input type="checkbox" name="selected[]" value="..."/>
}
```

---

## Actions (`Actions/`)

### `GridActions`

```php
class GridActions
{
    public function __construct(DataGridInterface $grid);
    public function setValueColumn(string|GridColumnInterface|NULL $column): self;
    public function getValueColumn(): ?GridColumnInterface;
    public function getFormActionFieldName(): string;  // returns 'grid_action'
    public function getFormSelectionFieldName(): string;  // returns 'selected'
    public function add(string $name, string $label): RegularAction;
    public function separator(): self;
    public function getActions(): array; // GridActionInterface[]
    public function hasActions(): bool;
    public function processSubmittedActions(?array $postData = null): bool;  // null → reads $_POST; [] → empty array
}
```

### `GridActionInterface` (interface)

```php
interface GridActionInterface {}
```

### `RegularAction`

```php
class RegularAction implements GridActionInterface
{
    public function __construct(string $name, string $label);
    public function getName(): string;
    public function getLabel(): string;
    public function setCallback(callable $callback): self;
    public function getCallback(): ?callable;
    public function hasCallback(): bool;
}
```

### `SeparatorAction`

```php
class SeparatorAction implements GridActionInterface {}
```

---

## Sorting (`Sorting/`)

### `SortManagerInterface` (interface)

```php
interface SortManagerInterface
{
    /**
     * @param GridRowInterface[] $rows
     */
    public function sortRows(array &$rows): void;
    public function getSortColumn(): ?GridColumnInterface;
    public function getSortDir(): string;                         // returns SORT_ASC or SORT_DESC
    public function getSortURL(GridColumnInterface $column): string;  // toggles direction for active column, else ASC; always apply htmlspecialchars() outside HTMLTag contexts
    public function isSortedBy(GridColumnInterface $column): bool;
    public function setColumnParam(string $param): self;          // default: 'sort'
    public function setDirectionParam(string $param): self;       // default: 'sort_dir'
    public function getColumnParam(): string;
    public function getDirectionParam(): string;
}
```

### `SortManager`

Resolves sort state lazily from `$_GET` on first access. URL building follows the same `parse_url` / `http_build_query` pattern as `ArrayPagination`.

```php
class SortManager implements SortManagerInterface
{
    public function __construct(DataGridInterface $grid);

    // SortManagerInterface — all methods delegated to lazy resolveSortState()
    public function getSortColumn(): ?GridColumnInterface;
    public function getSortDir(): string;
    public function getSortURL(GridColumnInterface $column): string; // plain URL — always apply htmlspecialchars() outside HTMLTag contexts
    public function isSortedBy(GridColumnInterface $column): bool;
    public function setColumnParam(string $param): self;
    public function setDirectionParam(string $param): self;
    public function getColumnParam(): string;
    public function getDirectionParam(): string;

    // Row sorting — called by DataGrid::generateOutput()
    // Partitions StandardRow instances, sorts them in-place, preserves non-standard row positions.
    // No-op for Manual mode or when no sort column is active.
    public function sortRows(array &$rows): void; // @param GridRowInterface[] $rows
}
```

---

## Renderer (`Renderer/`)

### `GridRendererInterface` (interface)

```php
interface GridRendererInterface
{
    public function renderGridFormTop(GridForm $form): string|StringableInterface;
    public function renderGridFormBottom(GridForm $form): string|StringableInterface;
    public function renderGridTop(): string|StringableInterface;
    public function renderGridBottom(): string|StringableInterface;
    public function renderBody(array $rows, array $columns): string|StringableInterface;
    public function renderFooterTop(GridFooter $footer): string|StringableInterface;
    public function renderFooterBottom(GridFooter $footer): string|StringableInterface;
    public function renderActionsRow(GridActions $actions): string|StringableInterface;
    public function renderSeparatorAction(SeparatorAction $action): string|StringableInterface;
    public function renderActionOption(RegularAction $action): string|StringableInterface;
    public function renderHeaderRowRepeated(HeaderRow $row, array $columns): string|StringableInterface;
    public function renderHeaderTop(GridHeader $header): string|StringableInterface;
    public function renderHeaderBottom(GridHeader $header): string|StringableInterface;
    public function renderHeaderRow(HeaderRow $row, array $columns): string|StringableInterface;
    public function renderHeaderCell(GridColumnInterface $column): string|StringableInterface;
    public function renderStandardRow(StandardRow $row, array $columns): string|StringableInterface;
    public function renderMergedRow(MergedRow $row, int $colspan): string|StringableInterface;
    public function renderCustomRow(GridRowInterface $row, array $columns): string|StringableInterface;
    public function renderRowCell(RegularCell $cell): string|StringableInterface;
    public function renderSelectionCell(SelectionCell $cell): string|StringableInterface;
    public function renderSelectionHeaderCell(): string|StringableInterface;
    public function renderPaginationRow(GridPagination $pagination): string|StringableInterface;
}
```

### `BaseGridRenderer` (abstract)

```php
abstract class BaseGridRenderer implements GridRendererInterface
{
    public function __construct(DataGridInterface $grid);
    abstract protected function init(): void;

    // All GridRendererInterface methods implemented with default HTML rendering.
    // Key protected helpers:
    protected function createForm(GridForm $form): HTMLTag;
    protected function createHiddenVariableWrapper(GridForm $form): HTMLTag;
    protected function createHeader(GridHeader $header): HTMLTag;
    protected function createHeaderRow(HeaderRow $row, array $columns): HTMLTag;
    protected function createHeaderRowRepeated(HeaderRow $row, array $columns): HTMLTag;
    // (WP-003) Sort-aware: plain text for non-sortable columns; delegates to createSortAnchor() for sortable columns.
    protected function createHeaderCell(GridColumnInterface $column): HTMLTag;
    // (WP-003) Builds <a href="getSortURL(col)"> with label and optional <span>▲|▼</span> indicator when isSortedBy(col).
    // $extraClasses are added to the <a> element (used by Bootstrap5Renderer override).
    // @param string[] $extraClasses
    protected function createSortAnchor(GridColumnInterface $column, array $extraClasses = []): HTMLTag;
    protected function createFooter(GridFooter $footer): HTMLTag;
    protected function renderHeaderCells(array $columns): string|StringableInterface;
    protected function createStandardRow(StandardRow $row, array $columns): HTMLTag;
    public function renderStandardRowCells(StandardRow $row, array $columns): string|StringableInterface;
    protected function createMergedRow(MergedRow $row, int $colspan): HTMLTag;
    protected function createMergedRowCell(MergedRow $row, int $colspan): HTMLTag;
    protected function createRowCell(RegularCell $cell): HTMLTag;

    // Colspan helper (WP-002)
    // Returns countColumns() + 1 when actions are defined, countColumns() otherwise.
    protected function getColspan(): int;

    // Selection cell rendering (WP-002)
    public function renderSelectionHeaderCell(): string|StringableInterface;
    protected function createSelectionHeaderCell(): HTMLTag;
    public function renderSelectionCell(SelectionCell $cell): string|StringableInterface;
    protected function createSelectionCell(SelectionCell $cell): HTMLTag;

    // Pagination rendering (WP-005)
    public function renderPaginationRow(GridPagination $pagination): string|StringableInterface;
    protected function createPaginationRow(GridPagination $pagination): HTMLTag;
    protected function createPreviousLink(GridPagination $pagination): HTMLTag;
    protected function createNextLink(GridPagination $pagination): HTMLTag;
    protected function createPageLink(int $page, string $url, bool $isCurrent): HTMLTag;
    protected function createEllipsis(): HTMLTag;
    // XSS-safe: $inputId and $urlTemplate are encoded with json_encode() before interpolation into the JS onclick string.
    protected function createPageJumpInput(GridPagination $pagination): HTMLTag;
    // Template method hook — returns a plain <span> wrapper by default; override to customise the container element.
    protected function createPageJumpContainer(HTMLTag $input, HTMLTag $button): HTMLTag;
}
```

### `RendererManager`

```php
class RendererManager
{
    public function __construct(DataGridInterface $grid);
    public function selectDefault(): DefaultRenderer;
    public function selectBootstrap5(): Bootstrap5Renderer;
    public function selectByClass(string $class): GridRendererInterface;
    public function selectRenderer(GridRendererInterface $renderer): self;
    public function getRenderer(): GridRendererInterface;
}
```

### Renderer Types

#### `DefaultRenderer`

```php
class DefaultRenderer extends BaseGridRenderer
{
    protected function init(): void;
    public function renderCustomRow(GridRowInterface $row, array $columns): string;
}
```

#### `Bootstrap5Renderer`

```php
class Bootstrap5Renderer extends BaseGridRenderer
{
    protected function init(): void;  // adds 'table' CSS class
    public function makeStriped(): self;
    public function makeHover(): self;
    public function makeBordered(): self;
    public function makeCompact(): self;
    public function renderCustomRow(GridRowInterface $row, array $columns): string;

    // Sort header (WP-003) — Bootstrap 5 override: injects Bootstrap utility classes
    // (text-decoration-none text-reset d-inline-flex align-items-center gap-1) into the <a> anchor
    // by overriding createSortAnchor() and delegating to parent with extra classes.
    // Non-sortable columns fall through to the inherited parent::createHeaderCell().
    // @param string[] $extraClasses
    protected function createSortAnchor(GridColumnInterface $column, array $extraClasses = []): HTMLTag;

    // Pagination (WP-005) — Bootstrap 5 override of BaseGridRenderer::renderPaginationRow()
    // Produces <tr><td colspan><nav aria-label="Page navigation"><ul class="pagination">...
    public function renderPaginationRow(GridPagination $pagination): string|StringableInterface;
    // Protected override (template method hook from BaseGridRenderer):
    protected function createPageJumpContainer(HTMLTag $input, HTMLTag $button): HTMLTag; // applies Bootstrap classes to $input/$button; wraps in <div class="d-flex align-items-center gap-2 mt-2">
    // Private helpers (not overridable):
    // createBootstrapPaginationRow(), createBootstrapPreviousItem(),
    // createBootstrapNextItem(), createBootstrapPageItem(),
    // createBootstrapEllipsisItem()
}
```

---

## Form (`Form/`)

### `GridForm`

```php
class GridForm implements ClassableInterface, IDInterface
{
    // Traits: ClassableTrait, IDTrait

    public function addHiddenVar(string $name, string|int|float|bool $value, ?string $id = null): HiddenVar;
    public function registerHiddenVar(HiddenVar $var): self;
    public function setHiddenVars(array $vars): self;  // @param array<string,string|int|bool> $vars
    public function getHiddenVars(): array; // HiddenVar[]
}
```

### `HiddenVar`

```php
class HiddenVar implements RenderableInterface
{
    // Traits: RenderableTrait

    public function __construct(string $name, string|int|float|bool $value, ?string $id = null);
    public function getName(): string;
    public function getValue(): string;
    public function setValue(string|int|float|bool $value): self;
    public function getID(): ?string;
    public function setID(?string $id): self;
    public function render(): string;
}
```

---

## Options (`Options/`)

### `GridOptions`

```php
class GridOptions
{
    public function setEmptyMessage(string|StringableInterface $message): self;
    public function setRepeatHeader(bool $enabled, ?int $threshold = null): self;
    public function getEmptyMessage(): string;
    public function isHeaderRepeated(int $count): bool;
    public function isHeaderRowEnabled(): bool;
    public function setHeaderRowEnabled(bool $enabled): self;
}
```

---

## Header / Footer

### `GridHeader`

```php
class GridHeader implements ClassableInterface, IDInterface
{
    // Traits: ClassableTrait, IDTrait
}
```

### `GridFooter`

```php
class GridFooter implements ClassableInterface, IDInterface
{
    // Traits: ClassableTrait, IDTrait
}
```

---

## Traits (`Traits/`)

### `AlignInterface`

```php
interface AlignInterface
{
    public const ALIGN_CENTER = 'center';
    public const ALIGN_RIGHT = 'right';
    public const ALIGN_LEFT = 'left';
    public const ALIGNS = [self::ALIGN_LEFT, self::ALIGN_CENTER, self::ALIGN_RIGHT];

    public function alignRight(): self;
    public function alignLeft(): self;
    public function alignCenter(): self;
    public function setAlign(?string $align): self;
    public function getAlign(): ?string;
}
```

### `AlignTrait`

Implements `AlignInterface`. Throws `GridColumnException::INVALID_ALIGN_VALUE` on invalid align values.

### `IDInterface`

```php
interface IDInterface
{
    public function setID(?string $id): self;
    public function getID(): ?string;
    public function requireID(): string;
}
```

### `IDTrait`

Implements `IDInterface`. Auto-generates an ID via `JSHelper::nextElementID()` when `requireID()` is called and no ID has been set.

---

## Pagination (`Pagination/`)

### `PaginationInterface` (interface)

Lean provider contract — only 4 methods. Derived values are computed by `GridPagination`.

```php
interface PaginationInterface
{
    public function getTotalItems(): int;
    public function getItemsPerPage(): int;
    public function getCurrentPage(): int;
    public function getPageURL(int $page): string;
}
```

### `GridPagination`

Grid-side manager that wraps a `PaginationInterface` provider and computes derived pagination values.

```php
class GridPagination
{
    public const PAGE_SENTINEL = 999_999_999_999;  // public — usable by external pagination providers

    public function __construct(DataGridInterface $grid);
    public function getGrid(): DataGridInterface;

    // Provider management
    public function setProvider(PaginationInterface $provider): self;
    public function getProvider(): PaginationInterface;  // throws DataGridException::ERROR_NO_PAGINATION_PROVIDER if unset
    public function hasProvider(): bool;

    // Computed properties
    public function getTotalPages(): int;              // 0 when no items
    public function getCurrentPage(): int;             // clamped to [1, getTotalPages()]
    public function hasPreviousPage(): bool;
    public function hasNextPage(): bool;
    public function getPreviousPageURL(): string;      // ⚠ no bounds guard — caller must check hasPreviousPage() first
    public function getNextPageURL(): string;          // ⚠ no bounds guard — caller must check hasNextPage() first
    public function getPageURL(int $page): string;
    public function getPageNumbers(): array;           // array<int|null> with null ellipsis sentinels
    public function getPageURLTemplate(): string;      // uses PAGE_SENTINEL (999_999_999_999) and replaces it with {PAGE}

    // Page number range controls
    public function setAdjacentCount(int $count): self; // ⚠ no validation — negative values produce undefined behaviour
    public function setEdgeCount(int $count): self;     // ⚠ no validation — negative values produce undefined behaviour

    // Jump-to-page
    public function isPageJumpEnabled(): bool;
    public function setPageJumpEnabled(bool $enabled): self;
}
```

### `ArrayPagination` — `Pagination\Types\ArrayPagination`

Array-backed pagination provider. Slices a full array to the requested page.

```php
class ArrayPagination implements PaginationInterface
{
    /**
     * @param array<mixed> $items
     */
    public function __construct(
        array $items,
        int $itemsPerPage = 25,
        ?int $currentPage = null,   // reads $_GET[$pageParam] when null
        string $pageParam = 'page'
    );

    // PaginationInterface
    public function getTotalItems(): int;
    public function getItemsPerPage(): int;
    public function getCurrentPage(): int;   // clamped to valid range
    public function getPageURL(int $page): string;  // rewrites REQUEST_URI query string — root-relative only (no scheme/host)

    // Slicing
    public function getSlicedItems(): array;  // array<mixed>

    // Helpers
    public function getPageParameterName(): string;
}
```
