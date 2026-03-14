# App DataGrids Changelog

## v0.3.0 - Built-in items-per-page selector
- Added `GridPagination::setItemsPerPageOptions()` to configure available page-size choices.
- Added `GridPagination::resolveItemsPerPage()` — lazy resolution chain: `$_GET[param]` → `GridSettings` → caller-supplied default. Valid `$_GET` values are validated against the options whitelist and auto-persisted.
- Added `GridPagination::getItemsPerPageURLTemplate()` / `getItemsPerPageURL()` — URL building with an `{IPP}` sentinel placeholder (mirrors the existing `{PAGE}` pattern).
- Added `GridPagination::setItemsPerPageParam()` — defaults to `ipp`; change if it conflicts with another GET parameter.
- Added `GridPagination::setDefaultItemsPerPage()` / `getDefaultItemsPerPage()` — configure the fallback value without passing it to every `resolveItemsPerPage()` call.
- Added `BaseGridRenderer::createItemsPerPageSelector()` — renders a `<select>` with an `onchange` JS handler; appended after the page-jump input when options are configured.
- `Bootstrap5Renderer` overrides `createItemsPerPageSelector()` — wraps the `<select>` in a `d-flex align-items-center gap-2 mt-2` container with `form-select form-select-sm` classes.
- Pagination row visibility: when IPP options are configured, the pagination row is shown even on zero-row or single-page grids so users can always change the page size. Existing grids without `setItemsPerPageOptions()` are unaffected.

## v0.2.0 - List IDs and Settings
- Added grid IDs to identify the same lists.
- Added grid settings storage.
- Added the items per page grid setting.

## v0.1.0 - Initial minimal release
- Initial release
