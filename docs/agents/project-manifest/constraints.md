# Constraints & Conventions

## Project Status

**Work in progress.** The README explicitly states this. Several methods have empty or stub implementations:

| Method | File | Notes |
|---|---|---|
| `DataGrid::getSortColumn()` | `DataGrid.php` | Empty body, should return `?GridColumnInterface` |
| `DataGrid::getSortDir()` | `DataGrid.php` | Empty body, should return `string` |
| `BaseGridColumn::useCallbackSorting()` | `BaseGridColumn.php` | Contains `// TODO` comment |
| `BaseGridColumn::useManualSorting()` | `BaseGridColumn.php` | Contains `// TODO` comment |
| `GridActions::render()` | `GridActions.php` | Empty body |
| `StandardRow::getSelectionCell()` | `StandardRow.php` | Empty body |
| `DefaultRenderer::renderCustomRow()` | `DefaultRenderer.php` | Returns empty string |
| `Bootstrap5Renderer::renderCustomRow()` | `Bootstrap5Renderer.php` | Returns empty string |

## Namespace Anomaly

`StandardRow` uses the namespace `WebcomicsBuilder\Grids\Rows\Types` instead of the expected `AppUtils\Grids\Rows\Types`. This is a legacy artifact (likely from a prior project). Because the project uses `classmap` autoloading, the class is still resolved correctly, but references to it from other files import from the wrong namespace:

- `BaseCell.php` → `use WebcomicsBuilder\Grids\Rows\Types\StandardRow;`
- `GridCellInterface.php` → `use WebcomicsBuilder\Grids\Rows\Types\StandardRow;`
- `RegularCell.php` → `use WebcomicsBuilder\Grids\Rows\Types\StandardRow;`
- `RowManager.php` → `use WebcomicsBuilder\Grids\Rows\Types\StandardRow;`
- `BaseGridRenderer.php` → `use WebcomicsBuilder\Grids\Rows\Types\StandardRow;`
- `GridRendererInterface.php` → `use WebcomicsBuilder\Grids\Rows\Types\StandardRow;`

## Autoloading

The project uses Composer **classmap** autoloading (not PSR-4):

```json
"autoload": {
    "classmap": ["src"]
}
```

This means `composer dump-autoload` must be run whenever files or classes are added/renamed/moved.

## PHP Version

- `composer.json` requires `php >= 8.4`
- `README.md` states PHP 8.2 — these are inconsistent; the `composer.json` value is authoritative.

## Coding Conventions

1. **Strict types everywhere:** All files declare `declare(strict_types=1);`.
2. **Fluent interface:** All setter/configuration methods return `self` or `$this`.
3. **Interface + abstract base + concrete types:** Every major component follows the pattern `Interface → abstract Base class → concrete Types/`.
4. **Trait pairing:** Traits always have a matching interface (`AlignTrait` ↔ `AlignInterface`, `IDTrait` ↔ `IDInterface`). Classes that use a trait must implement the corresponding interface.
5. **Error codes:** Exception classes define integer constants for specific error conditions (e.g., `GridColumnException::INVALID_COLUMN_NAME = 171602`).
6. **Column name validation:** Column names must start with a letter and contain only alphanumeric characters, hyphens, and underscores (regex: `/^[a-z][a-z0-9\-_]*$/i`).
7. **Static factory:** `DataGrid::create()` provides a static factory as an alternative to `new DataGrid()`.
8. **HTMLTag usage:** All HTML output is generated through the `HTMLTag` class from `application-utils-core`, not through string concatenation.
9. **Lazy initialization:** Some components are created on first access (e.g., `GridActions` via `DataGrid::actions()`, `HeaderRow` via `RowManager::getHeaderRow()`).
10. **Auto-generated IDs:** `IDTrait::requireID()` auto-generates an ID using `JSHelper::nextElementID()` when none has been set.

## Static Analysis

PHPStan is configured at **level 6** with the PHPUnit extension. Run via:

```bash
composer analyze
```

## Testing

PHPUnit 13 is configured. Run via:

```bash
composer test
```

No test files were found in the current codebase — tests have not yet been written.

## Dependencies on `application-utils-core`

The library heavily relies on `mistralys/application-utils-core` for:

- `HTMLTag` — HTML element generation
- `ClassableTrait` / `ClassableInterface` — CSS class management
- `RenderableBufferedTrait` / `RenderableInterface` / `RenderableTrait` — output rendering
- `StringableInterface` — string-castable objects
- `BaseException` — base exception class
- `JSHelper` — element ID generation
- `NumberInfo` / `parseNumber()` — numeric value handling with units
- `ClassHelper` — type-safe casting utilities
- `t()` — translation/localization function
