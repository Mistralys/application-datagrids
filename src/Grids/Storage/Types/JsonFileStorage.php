<?php

declare(strict_types=1);

namespace AppUtils\Grids\Storage\Types;

use AppUtils\FileHelper\JSONFile;
use AppUtils\Grids\DataGridException;
use AppUtils\Grids\Storage\GridStorageInterface;

class JsonFileStorage implements GridStorageInterface
{
    private string $storagePath;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $cache = [];

    public function __construct(string $storagePath)
    {
        $this->storagePath = $storagePath;

        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }
    }

    public function get(string $gridID, string $key, mixed $default = null): mixed
    {
        $data = $this->readGridData($gridID);
        return $data[$key] ?? $default;
    }

    public function set(string $gridID, string $key, mixed $value): void
    {
        $data = $this->readGridData($gridID);
        $data[$key] = $value;
        $this->cache[$gridID] = $data;

        JSONFile::factory($this->getFilePath($gridID))
            ->putData($data, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function readGridData(string $gridID): array
    {
        if (isset($this->cache[$gridID])) {
            return $this->cache[$gridID];
        }

        $jsonFile = JSONFile::factory($this->getFilePath($gridID));

        if (!$jsonFile->exists()) {
            $this->cache[$gridID] = [];
            return [];
        }

        /** @var array<string, mixed> $data */
        $data = $jsonFile->getData();
        $this->cache[$gridID] = $data;
        return $data;
    }

    private function getFilePath(string $gridID): string
    {
        $this->validateGridID($gridID);
        return $this->storagePath . '/' . $gridID . '.json';
    }

    private function validateGridID(string $gridID): void
    {
        if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $gridID) !== 1) {
            throw new DataGridException(
                sprintf(
                    'Invalid grid ID [%s]: must be a non-empty kebab-case string.',
                    $gridID
                ),
                null,
                DataGridException::ERROR_INVALID_GRID_ID
            );
        }
    }
}
