# Application Data Grids

PHP classes to abstract the rendering of HTML tables, with 
switchable renderers and a straightforward fluid and chainable 
API.

> **NOTE:** Work in progress, but the examples work already.

## Requirements

- PHP 8.4 or higher
- [Composer](https://getcomposer.org)
- Webserver

## Viewing the examples

1. Clone the repository.
2. Run `composer install` to install the dependencies.
3. Open the `examples` folder through your webserver.

Each example uses a shared `JsonFileStorage` instance (created in `examples/bootstrap.php`) to persist per-grid settings. The `examples/storage/` directory is created automatically on first use — no manual setup is required.

## Usage

### Creating a grid

Every `DataGrid` instance requires a **string ID** (strict kebab-case: `^[a-z][a-z0-9]*(-[a-z0-9]+)*$`) and a **storage handler** that implements `GridStorageInterface`. The library ships a file-backed implementation, `JsonFileStorage`:

```php
use AppUtils\Grids\DataGrid;
use AppUtils\Grids\Storage\Types\JsonFileStorage;

$storage = new JsonFileStorage('/path/to/storage/dir');
$grid = DataGrid::create('my-grid', $storage);

$grid->columns()->add('name', 'Name');
$grid->columns()->add('status', 'Status');

$grid->rows()->addArray(['name' => 'Alice', 'status' => 'Active']);
$grid->rows()->addArray(['name' => 'Bob',   'status' => 'Inactive']);

echo $grid;
```

The `JsonFileStorage` constructor creates the storage directory if it does not exist. One `JsonFileStorage` instance can be shared across all grids on a page; data is keyed by grid ID internally.

### Per-grid settings (items per page)

Use `$grid->settings()` to read and write typed per-grid settings persisted via the storage handler. The first available setting is **items per page** for pagination:

```php
// Read the stored value, falling back to 25 if nothing is persisted yet:
$itemsPerPage = $grid->settings()->getItemsPerPage(25);

// Build the pagination provider with the effective value:
$provider = new \AppUtils\Grids\Pagination\Types\ArrayPagination($allItems, $itemsPerPage);
$grid->pagination()->setProvider($provider);
$grid->rows()->addArrays($provider->getSlicedItems());

// Persist an updated preference (e.g. after a user submits a preference form):
$grid->settings()->setItemsPerPage(50);
```

Settings are stored per grid ID, so multiple grids on the same page are fully isolated. To use a custom storage backend (database, session, Redis, etc.), implement the two-method `GridStorageInterface`:

```php
use AppUtils\Grids\Storage\GridStorageInterface;

class SessionStorage implements GridStorageInterface
{
    public function get(string $gridID, string $key, mixed $default = null): mixed
    {
        return $_SESSION['grids'][$gridID][$key] ?? $default;
    }

    public function set(string $gridID, string $key, mixed $value): void
    {
        $_SESSION['grids'][$gridID][$key] = $value;
    }
}
```
