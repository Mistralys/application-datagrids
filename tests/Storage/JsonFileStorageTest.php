<?php

declare(strict_types=1);

namespace AppUtils\Tests\Storage;

use AppUtils\Grids\Storage\Types\JsonFileStorage;
use PHPUnit\Framework\TestCase;

class JsonFileStorageTest extends TestCase
{
    private string $storagePath;

    protected function setUp(): void
    {
        $this->storagePath = sys_get_temp_dir() . '/json-file-storage-test-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->storagePath)) {
            return;
        }

        foreach (glob($this->storagePath . '/*.json') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->storagePath);
    }

    // AC: set() a value, then get() it back — assert equality
    public function testReadWriteRoundTrip(): void
    {
        $storage = new JsonFileStorage($this->storagePath);

        $storage->set('my-grid', 'items_per_page', 25);

        $this->assertSame(25, $storage->get('my-grid', 'items_per_page'));
    }

    // AC: get() a key that was never set — assert $default is returned
    public function testDefaultFallback(): void
    {
        $storage = new JsonFileStorage($this->storagePath);

        $this->assertSame(10, $storage->get('my-grid', 'missing_key', 10));
    }

    // AC: get() returns null when no default is given and key is absent
    public function testDefaultFallbackIsNull(): void
    {
        $storage = new JsonFileStorage($this->storagePath);

        $this->assertNull($storage->get('my-grid', 'missing_key'));
    }

    // AC: assert no file exists before set(), file exists after
    public function testFileCreatedOnFirstWrite(): void
    {
        $storage = new JsonFileStorage($this->storagePath);
        $expectedFile = $this->storagePath . '/my-grid.json';

        $this->assertFileDoesNotExist($expectedFile);

        $storage->set('my-grid', 'items_per_page', 5);

        $this->assertFileExists($expectedFile);
    }

    // AC: Set two different keys for the same grid ID, read both back
    public function testMultipleKeysPerGrid(): void
    {
        $storage = new JsonFileStorage($this->storagePath);

        $storage->set('my-grid', 'items_per_page', 20);
        $storage->set('my-grid', 'sort_column', 'name');

        $this->assertSame(20, $storage->get('my-grid', 'items_per_page'));
        $this->assertSame('name', $storage->get('my-grid', 'sort_column'));
    }

    // AC: Set values for two different grid IDs, assert they don't interfere
    public function testMultipleGridsAreIsolated(): void
    {
        $storage = new JsonFileStorage($this->storagePath);

        $storage->set('grid-one', 'items_per_page', 10);
        $storage->set('grid-two', 'items_per_page', 50);

        $this->assertSame(10, $storage->get('grid-one', 'items_per_page'));
        $this->assertSame(50, $storage->get('grid-two', 'items_per_page'));
    }

    // AC: Provide a non-existent storage path, construct JsonFileStorage — assert directory is created
    public function testDirectoryCreatedOnConstruct(): void
    {
        $nonExistentPath = $this->storagePath . '/nested/sub';

        $this->assertDirectoryDoesNotExist($nonExistentPath);

        new JsonFileStorage($nonExistentPath);

        $this->assertDirectoryExists($nonExistentPath);

        // Cleanup nested dirs
        rmdir($nonExistentPath);
        rmdir($this->storagePath . '/nested');
    }

    // AC: validateGridID rejects path traversal attempts
    public function testInvalidGridIDPathTraversal(): void
    {
        $storage = new JsonFileStorage($this->storagePath);

        $this->expectException(\AppUtils\Grids\DataGridException::class);
        $storage->get('../escape', 'key');
    }

    // AC: validateGridID rejects IDs with spaces
    public function testInvalidGridIDWithSpace(): void
    {
        $storage = new JsonFileStorage($this->storagePath);

        $this->expectException(\AppUtils\Grids\DataGridException::class);
        $storage->get('my grid', 'key');
    }

    // AC: validateGridID rejects empty string
    public function testInvalidGridIDEmpty(): void
    {
        $storage = new JsonFileStorage($this->storagePath);

        $this->expectException(\AppUtils\Grids\DataGridException::class);
        $storage->get('', 'key');
    }

    // AC: validateGridID rejects uppercase letters (no longer valid under kebab-case)
    public function testInvalidGridIDUppercase(): void
    {
        $storage = new JsonFileStorage($this->storagePath);

        $this->expectException(\AppUtils\Grids\DataGridException::class);
        $storage->get('Grid_1', 'key');
    }

    // AC: validateGridID rejects IDs with underscores
    public function testInvalidGridIDUnderscore(): void
    {
        $storage = new JsonFileStorage($this->storagePath);

        $this->expectException(\AppUtils\Grids\DataGridException::class);
        $storage->get('my_grid', 'key');
    }

}
