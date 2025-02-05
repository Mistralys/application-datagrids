<?php

declare(strict_types=1);

namespace AppUtils\Examples\Grids;

use AppUtils\Grids\DataGrid;

require_once __DIR__.'/bootstrap.php';

$grid = DataGrid::create();

$grid->columns()
    ->addInteger('id', 'ID');

$grid->columns()
    ->add('label', 'Label')
    ->setWidth('50%');

$grid->columns()
    ->add('actions', 'Actions')
    ->alignRight();

$row = $grid->rows()->addArray(array(
    'id' => 1,
    'label' => 'First row',
    'actions' => '<button>Action</button>',
));

// Adding a single row returns the row object,
// which allows customizing the row as well
// as its cells.
$row->getCell('label')->setID('first-row-label');

// Adding multiple rows does not allow further
// customization.
$grid->rows()->addArrays(array(
    array(
        'id' => 2,
        'label' => 'Second row',
        'actions' => '<button>Action</button>',
    ),
    array(
        'id' => 3,
        'label' => 'Third row',
        'actions' => '<button>Action</button>',
    ),
));

$grid->rows()->addMerged('Merged row');

?><!doctype html>
<html lang="en">
<head>
    <title>Simple grid example</title>
    <style>
        BODY{
            padding: 2rem;
            font-family: sans-serif;
        }
        TABLE {
            border-collapse: collapse;
            border: solid 1px #000;
        }
        TD, TH {
            border: solid 1px #000;
            padding: 5px 8px;
        }
    </style>
</head>
<body>
    <?php echo $grid; ?>
</body>
</html>
