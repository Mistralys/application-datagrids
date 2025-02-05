<?php

declare(strict_types=1);

namespace AppUtils\Examples\Grids;

use AppUtils\Grids\DataGrid;

require_once __DIR__.'/bootstrap.php';

$grid = DataGrid::create();

// Select and configure the renderer
$grid->renderer()
    ->selectBootstrap5()
    ->makeBordered()
    ->makeHover();

$grid->columns()
    ->add('id', 'ID')
    ->setNowrap()
    ->setCompact()
    ->alignRight();

$grid->columns()
    ->add('label', 'Label')
    ->setWidth('50%');

$grid->columns()
    ->add('actions', 'Actions')
    ->alignRight();

$grid->rows()->addArrays(array(
    array(
        'id' => 1,
        'label' => 'First row',
        'actions' => '<button class="btn btn-secondary">Action</button>',
    ),
    array(
        'id' => 2,
        'label' => 'Second row',
        'actions' => '<button class="btn btn-secondary">Action</button>',
    ),
    array(
        'id' => 3,
        'label' => 'Third row',
        'actions' => '<button class="btn btn-secondary">Action</button>',
    ),
));

$grid->rows()->addMerged('Merged row');

?><!doctype html>
<html lang="en">
<head>
    <title>Bootstrap 5 grid example</title>
    <link rel="stylesheet" href="../vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
    <style>
        BODY{
            padding: 2rem;
        }
    </style>
</head>
<body>
    <main class="container">
        <h1>Bootstrap 5 grid</h1>
        <?php echo $grid; ?>
    </main>
</body>
</html>
