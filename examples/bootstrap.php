<?php

declare(strict_types=1);

if(!file_exists(__DIR__.'/../vendor/autoload.php')) {
    die('Please run `composer install` before using the examples.');
}

require_once __DIR__.'/../vendor/autoload.php';

use AppUtils\Grids\Storage\Types\JsonFileStorage;

$storage = new JsonFileStorage(__DIR__ . '/storage');
