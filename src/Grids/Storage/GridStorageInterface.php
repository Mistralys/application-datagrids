<?php

declare(strict_types=1);

namespace AppUtils\Grids\Storage;

interface GridStorageInterface
{
    public function get(string $gridID, string $key, mixed $default = null): mixed;
    public function set(string $gridID, string $key, mixed $value): void;
}
