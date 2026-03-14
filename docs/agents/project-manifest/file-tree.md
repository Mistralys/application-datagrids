# File Tree

```
application-datagrids/
├── composer.json                          # Package definition, dependencies, scripts
├── LICENSE                                # MIT license
├── phpstan.neon                           # PHPStan config (level 6, src/)
├── phpunit.xml                            # PHPUnit config (testsuite → tests/, failOnWarning=true; named suites: Settings, Storage, and the pre-existing suites)
├── README.md                              # Project overview & setup instructions
│
├── examples/                              # Runnable example pages (serve via webserver)
│   ├── bootstrap.php                      # Autoloader + shared JsonFileStorage bootstrap (exposes $storage to all examples)
│   ├── 1-simple-grid.php                  # Plain HTML table grid (grid ID: simple-grid)
│   ├── 2-bootstrap-grid.php              # Bootstrap 5 styled grid (grid ID: bootstrap-grid)
│   ├── 3-grid-actions.php                # Grid with row actions (grid ID: actions-demo)
│   ├── 4-pagination.php                  # Pagination with ArrayPagination (grid ID: pagination-demo)
│   └── storage/                           # [auto-created] Per-example JSON settings files; .gitignore prevents *.json from being committed
│
├── src/
│   └── Grids/
│       ├── DataGrid.php                   # Main entry point — creates and renders a grid
│       ├── DataGridException.php          # Base exception for the library
│       ├── DataGridInterface.php          # Public contract for DataGrid
│       │
│       ├── Actions/                       # Grid-level bulk actions (e.g. "Delete selected")
│       │   ├── GridActions.php            # Action manager — add/retrieve actions
│       │   └── Type/
│       │       ├── GridActionInterface.php
│       │       ├── RegularAction.php      # Named action with label
│       │       └── SeparatorAction.php    # Visual separator between actions
│       │
│       ├── Cells/                         # Individual cell representations
│       │   ├── BaseCell.php               # Abstract cell base class
│       │   ├── GridCellInterface.php      # Cell contract
│       │   ├── RegularCell.php            # Standard value cell (text, formatted)
│       │   └── SelectionCell.php          # Checkbox cell for row selection
│       │
│       ├── Columns/                       # Column definitions & management
│       │   ├── BaseGridColumn.php         # Abstract column base (name, label, width, sort, align)
│       │   ├── ColumnManager.php          # Add, register, and retrieve columns
│       │   ├── GridColumnException.php    # Column-specific exceptions
│       │   ├── GridColumnInterface.php    # Column contract
│       │   ├── SortMode.php               # Backed string enum: Native, Callback, Manual
│       │   └── Types/
│       │       ├── DefaultColumn.php      # Generic text column
│       │       └── IntegerColumn.php      # Right-aligned, compact numeric column
│       │
│       ├── Footer/
│       │   └── GridFooter.php             # Table footer (classable, ID-able)
│       │
│       ├── Form/                          # Wrapping <form> around the grid
│       │   ├── GridForm.php               # Form element with hidden variables
│       │   └── HiddenVar.php              # Single hidden <input> element
│       │
│       ├── Header/
│       │   └── GridHeader.php             # Table header (classable, ID-able)
│       │
│       ├── Options/
│       │   └── GridOptions.php            # Grid-level settings (empty message, repeat header)
│       │
│       ├── Pagination/                    # Pagination system (provider interface + implementations)
│       │   ├── PaginationInterface.php    # Provider contract (4 methods)
│       │   ├── GridPagination.php         # Grid-side manager: page calc, ranges, URL templates
│       │   └── Types/
│       │       └── ArrayPagination.php    # Array-backed provider (slice + URL rewrite)
│       │
│       ├── Sorting/                       # Sort state resolution & row ordering
│       │   ├── SortManager.php            # Resolves sort state from $_GET, builds sort URLs, sorts rows in-place
│       │   └── SortManagerInterface.php   # Public contract for sort state management
│       │
│       ├── Settings/                      # Typed accessors for per-grid settings (wraps GridStorageInterface)
│       │   └── GridSettings.php           # getItemsPerPage / setItemsPerPage; key 'items_per_page'
│       │
│       ├── Storage/                       # Per-grid persistent key-value settings storage
│       │   ├── GridStorageInterface.php   # Storage contract: get(gridID, key, default) and set(gridID, key, value)
│       │   └── Types/
│       │       └── JsonFileStorage.php    # File-backed implementation — {storagePath}/{gridID}.json, per-request memory cache, path-traversal-safe via validateGridID()
│       │
│       ├── Renderer/                      # Pluggable rendering system
│       │   ├── BaseGridRenderer.php       # Abstract renderer with full default HTML output
│       │   ├── GridRendererInterface.php  # Renderer contract
│       │   ├── RendererManager.php        # Renderer selection & instantiation
│       │   └── Types/
│       │       ├── DefaultRenderer.php    # Plain HTML renderer (no framework CSS)
│       │       └── Bootstrap5Renderer.php # Bootstrap 5 table renderer
│       │
│       ├── Rows/                          # Row definitions & management
│       │   ├── BaseGridRow.php            # Abstract row base (classable, ID-able)
│       │   ├── GridRowException.php       # Row-specific exceptions
│       │   ├── GridRowInterface.php       # Row contract
│       │   ├── RowManager.php             # Add, register, and retrieve rows
│       │   └── Types/
│       │       ├── HeaderRow.php          # Column header row (<th> cells)
│       │       ├── MergedRow.php          # Full-width merged content row (colspan)
│       │       └── StandardRow.php        # Regular data row with cell values
│       │
│       └── Traits/                        # Reusable traits & interfaces
│           ├── AlignInterface.php         # Alignment constants + methods
│           ├── AlignTrait.php             # Alignment implementation
│           ├── IDInterface.php            # HTML ID methods
│           └── IDTrait.php                # HTML ID implementation
│
├── vendor/                                # [auto-generated] Composer dependencies
│   └── ...
│
├── tests/                                 # PHPUnit test suite (91 tests, 197 assertions)
│   ├── bootstrap.php                      # Test bootstrap — requires vendor/autoload.php; glob-loads TestClasses/ so shared helpers are available to all suites
│   ├── TestClasses/                       # Shared test helper classes (glob-loaded by bootstrap.php; not in Composer classmap — deferred to WP-008)
│   │   └── InMemoryStorage.php           # In-memory GridStorageInterface implementation for unit tests
│   ├── Actions/                           # Tests for GridActions, action processing
│   │   └── GridActionsTest.php            # 7 tests: processSubmittedActions() — no data, empty array, missing field, unknown action, separator, callback, no callback
│   ├── Cells/                             # Tests for SelectionCell rendering
│   │   └── SelectionCellTest.php          # 2 tests: checkbox markup (type/name/value), throws DataGridException when value column missing
│   ├── Pagination/                        # Tests for GridPagination, ArrayPagination
│   │   └── GridPaginationTest.php         # 36 tests: page calc, clamping, page numbers, prev/next, URL template; (WP-002/WP-005) IPP options, resolveItemsPerPage (priority chain, persist, cache, invalid GET), IPP URL template/page-reset, custom param
│   │   └── ArrayPaginationTest.php        # 11 tests: slicing, URL params, totalItems, itemsPerPage, clamping
│   ├── Rows/                              # Tests for StandardRow, row selection
│   │   └── StandardRowTest.php            # 5 tests: getSelectValue (with/without column), isSelectable (with/without actions), throws DataGridException on empty value
│   ├── Settings/                          # Tests for GridSettings typed accessor
│   │   └── GridSettingsTest.php           # 6 tests: null default, explicit default, set/get roundtrip, fluent return, default override, per-gridID isolation
│   ├── Storage/                           # Tests for JsonFileStorage (file-backed storage implementation)
│   │   └── JsonFileStorageTest.php        # 7 tests: read/write round-trip, default fallback (value and null), file creation on first write, multiple keys per grid, multiple grids isolated, directory creation
│   └── Sorting/                           # Tests for SortManager, column sorting, renderer header cells
│       ├── ColumnSortingTest.php          # 18 tests: sortable flags, getSortColumn/getSortDir resolution, native/callback/manual row sorting, getSortURL toggling, merged-row position preservation
│       ├── SortManagerTest.php            # 8 tests: sortRows() native ASC/DESC, callback ASC/DESC (negation), manual no-op, no-sort-column no-op, MergedRow preservation, numeric native sort
│       └── RendererSortHeaderTest.php     # 5 tests: non-sortable no-link (base/BS5), sortable has <a> with sort URL, active sort indicator, BS5 utility classes on <a>
│
└── docs/
    └── agents/
        └── project-manifest/              # This manifest
```
