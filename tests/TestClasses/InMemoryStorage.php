<?php

declare(strict_types=1);

namespace AppUtils\Tests\TestClasses;

use AppUtils\Grids\Storage\GridStorageInterface;

class InMemoryStorage implements GridStorageInterface
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $storage = [];

    public function get(string $gridID, string $key, mixed $default = null): mixed
    {
        return $this->storage[$gridID][$key] ?? $default;
    }

    public function set(string $gridID, string $key, mixed $value): void
    {
        if (!isset($this->storage[$gridID])) {
            $this->storage[$gridID] = [];
        }

        $this->storage[$gridID][$key] = $value;
    }
}
