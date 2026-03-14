<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use AppUtils\Grids\DataGrid;
use AppUtils\Grids\Pagination\Types\ArrayPagination;

// 1. Generate a large dataset: 200 items
$categories = ['Alpha', 'Beta', 'Gamma', 'Delta'];
$statuses   = ['Active', 'Inactive', 'Pending'];
$allItems = [];
for ($i = 1; $i <= 200; $i++) {
    $allItems[] = [
        'id'       => $i,
        'title'    => 'Item #' . $i,
        'category' => $categories[($i - 1) % count($categories)],
        'status'   => $statuses[($i - 1) % count($statuses)],
    ];
}

// 2. Create the grid + Bootstrap 5 renderer
$grid = DataGrid::create('pagination-demo', $storage);
$grid->renderer()
    ->selectBootstrap5()
    ->makeBordered()
    ->makeHover();

// 3. Define columns
$grid->columns()->add('id', '#')->setCompact()->alignRight()->useNativeSorting();
$grid->columns()->add('title', 'Title')->setWidth('50%')->useNativeSorting();
$grid->columns()->add('category', 'Category');
$grid->columns()->add('status', 'Status');

// 4. Configure the available page-size options, then resolve the effective value.
//    Priority chain: $_GET['ipp'] → persisted GridSettings → default (15).
//    The resolved value is auto-persisted to GridSettings when it comes from $_GET.
$grid->pagination()->setItemsPerPageOptions([10, 15, 25, 50]);
$itemsPerPage = $grid->pagination()->resolveItemsPerPage(15);

// 5. Build the provider with the resolved items-per-page and attach it.
//    The IPP selector is rendered automatically next to the page-jump input.
$pagination = new ArrayPagination($allItems, $itemsPerPage);
$grid->pagination()->setProvider($pagination);

// 6. Configure display settings
$grid->pagination()->setAdjacentCount(2)->setEdgeCount(2)->setPageJumpEnabled(true);

// 7. Add ONLY the current page's items to the grid
$grid->rows()->addArrays($pagination->getSlicedItems());

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pagination Example</title>
    <link rel="stylesheet" href="../vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<h1>Pagination Example</h1>
<p>Showing page <?= $grid->pagination()->getCurrentPage() ?>
   of <?= $grid->pagination()->getTotalPages() ?>
   (<?= count($allItems) ?> total items)</p>
<?= $grid ?>
</body>
</html>
