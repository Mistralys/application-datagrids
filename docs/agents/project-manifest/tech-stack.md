# Tech Stack & Patterns

## Runtime & Language

| Item | Value |
|---|---|
| Language | PHP |
| Min. PHP version | 8.4 (composer.json `require`; README states 8.2) |
| Package manager | Composer |
| Autoloading | `classmap` (scans `src/`) — not PSR-4 |

## Dependencies (Runtime)

| Package | Version | Purpose |
|---|---|---|
| `mistralys/application-utils-core` | ≥ 2.5.0 | Core utility library — provides `HTMLTag`, `ClassableTrait`, `RenderableInterface`, `RenderableBufferedTrait`, `JSHelper`, `NumberInfo`, `BaseException`, translation function `t()`, etc. |
| `twbs/bootstrap` | ^5.3.3 | Bootstrap 5 CSS framework (used by `Bootstrap5Renderer` and examples). |

## Dependencies (Dev)

| Package | Version | Purpose |
|---|---|---|
| `phpunit/phpunit` | ^12.0 | Unit testing |
| `phpstan/phpstan` | ^2.1 | Static analysis |
| `phpstan/phpstan-phpunit` | ^2.0 | PHPStan extension for PHPUnit |
| `roave/security-advisories` | dev-latest | Security vulnerability checks |

## Build & Analysis Tools

| Tool | Config | Description |
|---|---|---|
| PHPStan | `phpstan.neon`, level 6, paths: `src/` | Static analysis with phpunit extension |
| PHPUnit | (default config) | Test runner |
| Composer scripts | See `composer.json` `scripts` | `analyze`, `test`, `test-file`, `test-filter`, etc. |

## Architectural Patterns

### Fluent / Chainable API
All configuration methods return `self` or `$this`, enabling method chaining:
```php
$grid->columns()->add('label', 'Label')->setWidth('50%')->alignRight();
```

### Manager Pattern
Dedicated manager classes aggregate and provide access to their child objects:
- `ColumnManager` — manages `GridColumnInterface` instances
- `RowManager` — manages `GridRowInterface` instances
- `RendererManager` — manages renderer selection

### Strategy Pattern (Renderers)
Rendering is decoupled from the grid via `GridRendererInterface`. Concrete renderers (`DefaultRenderer`, `Bootstrap5Renderer`) extend `BaseGridRenderer` and can be swapped at runtime through `RendererManager`.

### Template Method Pattern
Abstract base classes (`BaseGridColumn`, `BaseGridRow`, `BaseCell`, `BaseGridRenderer`) define skeleton logic and delegate to an abstract `init()` method implemented by concrete subclasses.

### Trait-Based Composition
Shared behaviors are extracted into traits with matching interfaces:
- `IDTrait` / `IDInterface` — HTML element ID management
- `AlignTrait` / `AlignInterface` — text alignment (left, center, right)
- `ClassableTrait` / `ClassableInterface` — CSS class management (from `application-utils-core`)
- `RenderableBufferedTrait` / `RenderableInterface` — output buffered rendering (from `application-utils-core`)

### Interface-Driven Design
All major components expose an interface (`DataGridInterface`, `GridColumnInterface`, `GridRowInterface`, `GridCellInterface`, `GridRendererInterface`, `GridActionInterface`) that defines the public contract.

## Root Namespace

`AppUtils\Grids` — all source classes live under this namespace.

> **Note:** `StandardRow` currently uses the namespace `WebcomicsBuilder\Grids\Rows\Types` instead of `AppUtils\Grids\Rows\Types`. This appears to be a legacy/naming artifact. The classmap autoloader resolves it regardless of PSR-4 compliance.
