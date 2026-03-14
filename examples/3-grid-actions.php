<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use AppUtils\Grids\DataGrid;

$feedback = null;

// 1. Create grid + Bootstrap 5 renderer
$grid = DataGrid::create('actions-demo', $storage);
$grid->renderer()
    ->selectBootstrap5()
    ->makeBordered()
    ->makeHover();

// 2. Define columns
$grid->columns()->add('id', '#')->setCompact()->alignRight();
$grid->columns()->add('label', 'Label');
$grid->columns()->add('status', 'Status');

// 3. Set the value column (primary key for selection)
$grid->actions()->setValueColumn($grid->columns()->getByName('id'));

// 4. Register actions with callbacks
$grid->actions()
    ->add('delete', 'Delete selected')
    ->setCallback(function (array $ids) use (&$feedback): void {
        $feedback = 'Deleted items: ' . implode(', ', $ids);
    });

$grid->actions()->separator();

$grid->actions()
    ->add('archive', 'Archive selected')
    ->setCallback(function (array $ids) use (&$feedback): void {
        $feedback = 'Archived items: ' . implode(', ', $ids);
    });

// 5. Add sample data rows
$rows = [
    ['id' => 1,  'label' => 'Alpha',   'status' => 'Active'],
    ['id' => 2,  'label' => 'Beta',    'status' => 'Inactive'],
    ['id' => 3,  'label' => 'Gamma',   'status' => 'Active'],
    ['id' => 4,  'label' => 'Delta',   'status' => 'Pending'],
    ['id' => 5,  'label' => 'Epsilon', 'status' => 'Active'],
    ['id' => 6,  'label' => 'Zeta',    'status' => 'Inactive'],
    ['id' => 7,  'label' => 'Eta',     'status' => 'Active'],
    ['id' => 8,  'label' => 'Theta',   'status' => 'Pending'],
    ['id' => 9,  'label' => 'Iota',    'status' => 'Active'],
    ['id' => 10, 'label' => 'Kappa',   'status' => 'Inactive'],
];

$grid->rows()->addArrays($rows);

// Process submitted actions before any HTML output,
// so that the callback has a chance to set $feedback.
$grid->processActions();
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Grid Actions Example</title>
    <link rel="stylesheet" href="../vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<h1>Grid Actions Example</h1>
<?php if ($feedback !== null): ?>
    <div class="alert alert-success"><?= htmlspecialchars($feedback) ?></div>
<?php endif; ?>
<?= $grid ?>
</body>
</html>
