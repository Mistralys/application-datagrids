<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Load test helper classes (not in the Composer classmap until WP-008)
foreach (glob(__DIR__ . '/TestClasses/*.php') ?: [] as $file) {
    require_once $file;
}
