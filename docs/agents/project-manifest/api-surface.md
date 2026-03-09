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
    public function getSortColumn(): ?GridColumnInterface;
    public function getSortDir(): string;
}
```

### `DataGridException`

```php
class DataGridException extends BaseException {}
```

---

## Columns (`Columns/`)

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
    public function isSortable(): bool;
    public function useCallbackSorting(callable $callback): GridColumnInterface;
    public function useManualSorting(): GridColumnInterface;
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
}
```

### `RowManager`

```php
class RowManager
{
    public function __construct(DataGrid $grid);
    public function getGrid(): DataGrid;
    public function addArrays(array $rows): self;
    public function addArray(array $columnValues): StandardRow;
    public function addMerged($content = null): MergedRow;
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

> **Note:** Currently in namespace `WebcomicsBuilder\Grids\Rows\Types` (see Constraints).

```php
class StandardRow extends BaseGridRow
{
    public function __construct(RowManager $manager, array $values = array());
    public function setValue(GridColumnInterface|string $column, mixed $value): self;
    public function getCell(GridColumnInterface|string $column): RegularCell;
    public function getValue(GridColumnInterface|string $column): mixed;
    public function setValues(array $values): GridRowInterface;
    public function isSelectable(): bool;
    public function getSelectionCell(): ?SelectionCell;
    public function getGrid(): DataGridInterface;
}
```

#### `MergedRow`

```php
class MergedRow extends BaseGridRow
{
    public function __construct(string|StringableInterface|NULL $content = null);
    public function setContent(string|StringableInterface|NULL $content): self;
    public function getContent(): string;
}
```

#### `HeaderRow`

```php
class HeaderRow extends BaseGridRow
{
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

```php
class SelectionCell extends BaseCell
{
    public function getID(): string;
    public function renderContent(): string;
}
```

---

## Actions (`Actions/`)

### `GridActions`

```php
class GridActions
{
    public function __construct(DataGrid $grid);
    public function setValueColumn(string|GridColumnInterface|NULL $column): self;
    public function add(string $name, string $label): RegularAction;
    public function separator(): self;
    public function getActions(): array; // RegularAction[]
    public function render(): string;
    public function hasActions(): bool;
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
}
```

### `SeparatorAction`

```php
class SeparatorAction implements GridActionInterface {}
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
    protected function createHeaderCell(GridColumnInterface $column): HTMLTag;
    protected function createFooter(GridFooter $footer): HTMLTag;
    protected function renderHeaderCells(array $columns): string|StringableInterface;
    protected function createStandardRow(StandardRow $row, array $columns): HTMLTag;
    protected function createMergedRow(MergedRow $row, int $colspan): HTMLTag;
    protected function createMergedRowCell(MergedRow $row, int $colspan): HTMLTag;
    protected function createRowCell(RegularCell $cell): HTMLTag;
}
```

### `RendererManager`

```php
class RendererManager
{
    public function __construct(DataGrid $grid);
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
    public function setHiddenVars(array $vars): self;
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
