# AGENTS.md — Application Data Grids

> **Operational rulebook for AI agents.** Read this file first when entering the codebase.
> It directs you to the Project Manifest (the source of truth) and encodes the rules
> you must follow to operate correctly and efficiently.

---

## 1. Project Manifest — Start Here!

**Location:** `docs/agents/project-manifest/`

| Document | Description |
|---|---|
| [README.md](docs/agents/project-manifest/README.md) | Project overview & manifest index |
| [tech-stack.md](docs/agents/project-manifest/tech-stack.md) | Runtime, dependencies, architectural patterns, build tools |
| [file-tree.md](docs/agents/project-manifest/file-tree.md) | Annotated directory structure of the entire project |
| [api-surface.md](docs/agents/project-manifest/api-surface.md) | Public constructors, properties, and method signatures for all classes/interfaces |
| [data-flows.md](docs/agents/project-manifest/data-flows.md) | Main interaction paths: grid creation, rendering pipeline, cell formatting, row selection, renderer selection |
| [constraints.md](docs/agents/project-manifest/constraints.md) | Coding conventions, known issues, namespace anomaly, stub inventory |

### Quick Start Workflow

1. **Read** `README.md` — understand the project purpose and scope.
2. **Understand** `tech-stack.md` — know the runtime, dependencies, and architectural patterns.
3. **Internalize** `constraints.md` — learn the rules, conventions, and known issues before touching code.
4. **Navigate** `file-tree.md` — locate files without scanning the filesystem.
5. **Reference** `api-surface.md` — look up public APIs without reading source files.
6. **Trace** `data-flows.md` — understand how data moves through the system.

---

## 2. Manifest Maintenance Rules

When you make a code change, you **MUST** update the corresponding manifest documents:

| Change Made | Documents to Update |
|---|---|
| New class or interface added | `api-surface.md`, `file-tree.md` |
| Class renamed or moved | `api-surface.md`, `file-tree.md`, `constraints.md` (if it affects the namespace anomaly) |
| Public method added or signature changed | `api-surface.md` |
| New dependency added/removed | `tech-stack.md` |
| Directory restructured | `file-tree.md` |
| New renderer type added | `api-surface.md`, `file-tree.md`, `data-flows.md` |
| New column or row type added | `api-surface.md`, `file-tree.md`, `data-flows.md` |
| Coding convention introduced or changed | `constraints.md` |
| Stub method implemented | `constraints.md` (remove from stub inventory) |
| Composer script added/changed | `tech-stack.md`, `AGENTS.md` (Composer Scripts section) |
| New example added | `file-tree.md` |
| Namespace anomaly fixed | `constraints.md`, `api-surface.md`, `tech-stack.md` |

---

## 3. Efficiency Rules — Search Smart

- **Finding files?** Check `file-tree.md` FIRST.
- **Understanding methods?** Check `api-surface.md` FIRST.
- **Implementation patterns?** Check `tech-stack.md` FIRST.
- **Known issues or conventions?** Check `constraints.md` FIRST.
- **Data flow questions?** Check `data-flows.md` FIRST.
- **Only then** read source files.

Do NOT grep or scan the `src/` directory for information that is already documented in the manifest. Every unnecessary file read wastes tokens and time.

---

## 4. Failure Protocol & Decision Matrix

| Scenario | Action | Priority |
|---|---|---|
| Ambiguous requirement | Use the most restrictive interpretation | MUST |
| Manifest/code conflict | Trust the manifest, flag the code for fix | MUST |
| Missing documentation | Flag the gap explicitly, do not invent facts | MUST |
| Untested code path | Proceed with caution, add a test recommendation | SHOULD |
| Stub method encountered | Do not assume it works — check `constraints.md` stub inventory | MUST |
| `classmap` autoloading | Run `composer dump-autoload` after adding/renaming/moving files | MUST |
| HTML output changes | Use `HTMLTag` from `application-utils-core`, never raw string concatenation | MUST |
| New trait created | Always create a matching interface (`FooTrait` ↔ `FooInterface`) | MUST |
| New setter method | Return `self`/`$this` to maintain the fluent API | MUST |
| New PHP file | Include `declare(strict_types=1);` at the top | MUST |

---

## 5. Composer Scripts

All scripts are defined in `composer.json` under `"scripts"`. Use these instead of running tools directly.

| Command | Description |
|---|---|
| `composer analyze` | Run PHPStan static analysis (level 6, 900 MB memory limit) |
| `composer analyze-save` | Run PHPStan and save output to `phpstan-result.txt` |
| `composer analyze-clear` | Clear PHPStan result cache |
| `composer test` | Run PHPUnit test suite |
| `composer test-file` | Run PHPUnit on a specific file (no progress output) |
| `composer test-suite` | Run a specific PHPUnit test suite (no progress output) |
| `composer test-filter` | Run PHPUnit with a filter (no progress output) |
| `composer test-group` | Run PHPUnit for a specific group (no progress output) |

### Underlying Commands

```text
analyze        → php vendor/bin/phpstan analyse --configuration phpstan.neon --memory-limit=900M
analyze-save   → php vendor/bin/phpstan analyse --configuration phpstan.neon --memory-limit=900M > phpstan-result.txt || true
analyze-clear  → php vendor/bin/phpstan clear-result-cache
test           → php vendor/bin/phpunit
test-file      → php vendor/bin/phpunit --no-progress
test-suite     → php vendor/bin/phpunit --no-progress --testsuite
test-filter    → php vendor/bin/phpunit --no-progress --filter
test-group     → php vendor/bin/phpunit --no-progress --group
```

> **Test suites:** Pagination (`GridPaginationTest`, `ArrayPaginationTest`), Actions (`GridActionsTest`), Rows (`StandardRowTest`), Cells (`SelectionCellTest`), Settings (`GridSettingsTest`), Sorting (`ColumnSortingTest`, `SortManagerTest`, `RendererSortHeaderTest`), Storage (`JsonFileStorageTest`).

---

## 6. Project Stats

| Attribute | Value |
|---|---|
| **Language** | PHP ≥ 8.4 |
| **Architecture** | Fluent API / Manager pattern / Strategy (renderers) / Interface-driven |
| **Root namespace** | `AppUtils\Grids` |
| **Package manager** | Composer |
| **Autoloading** | `classmap` (not PSR-4) — run `composer dump-autoload` after structural changes |
| **Test framework** | PHPUnit 12 |
| **Static analysis** | PHPStan level 6 (`phpstan.neon`) |
| **License** | MIT |
| **Status** | Work in progress |
