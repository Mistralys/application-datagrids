# File Tree

```
application-datagrids/
├── composer.json                          # Package definition, dependencies, scripts
├── LICENSE                                # MIT license
├── phpstan.neon                           # PHPStan config (level 6, src/)
├── phpunit.xml.dist                       # PHPUnit config (testsuite → tests/, failOnWarning=true)
├── README.md                              # Project overview & setup instructions
│
├── examples/                              # Runnable example pages (serve via webserver)
│   ├── bootstrap.php                      # Autoloader bootstrap for examples
│   ├── 1-simple-grid.php                  # Plain HTML table grid
│   ├── 2-bootstrap-grid.php              # Bootstrap 5 styled grid
│   ├── 3-grid-actions.php                # Grid with row actions (select + dropdown)
│   └── 4-pagination.php                  # Pagination with ArrayPagination (Bootstrap 5)
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
├── tests/                                 # PHPUnit test suite (47 tests, 69 assertions)
│   ├── bootstrap.php                      # Test bootstrap (requires vendor/autoload.php)
│   ├── Actions/                           # Tests for GridActions, action processing
│   │   └── GridActionsTest.php            # 7 tests: processSubmittedActions() — no data, empty array, missing field, unknown action, separator, callback, no callback
│   ├── Cells/                             # Tests for SelectionCell rendering
│   │   └── SelectionCellTest.php          # 2 tests: checkbox markup (type/name/value), empty-value HTMLTag omission
│   ├── Pagination/                        # Tests for GridPagination, ArrayPagination
│   │   ├── GridPaginationTest.php         # 20 tests: page calc, clamping, page numbers, prev/next, URL template
│   │   └── ArrayPaginationTest.php        # 13 tests: slicing, URL params, totalItems, itemsPerPage, clamping
│   └── Rows/                              # Tests for StandardRow, row selection
│       └── StandardRowTest.php            # 5 tests: getSelectValue (with/without column), isSelectable (with/without actions), E_USER_WARNING on empty value
│
└── docs/
    └── agents/
        └── project-manifest/              # This manifest
```
