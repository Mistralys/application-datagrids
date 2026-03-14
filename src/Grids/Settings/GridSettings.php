<?php

declare(strict_types=1);

namespace AppUtils\Grids\Settings;

use AppUtils\Grids\DataGridException;
use AppUtils\Grids\Storage\GridStorageInterface;

class GridSettings
{
    private const KEY_ITEMS_PER_PAGE = 'items_per_page';

    private string $gridID;
    private GridStorageInterface $storage;

    public function __construct(string $gridID, GridStorageInterface $storage)
    {
        $this->gridID = $gridID;
        $this->storage = $storage;
    }

    public function getItemsPerPage(?int $default = null): ?int
    {
        $value = $this->storage->get($this->gridID, self::KEY_ITEMS_PER_PAGE, $default);

        if ($value === null) {
            return null;
        }

        return (int)$value;
    }

    public function setItemsPerPage(int $value): self
    {
        if ($value < 1) {
            throw new DataGridException(
                sprintf('Items per page must be a positive integer, [%d] given.', $value),
                null,
                DataGridException::ERROR_INVALID_ITEMS_PER_PAGE
            );
        }

        $this->storage->set($this->gridID, self::KEY_ITEMS_PER_PAGE, $value);
        return $this;
    }
}
