<?php

declare(strict_types=1);

namespace AppUtils\Tests\Settings;

use AppUtils\Grids\DataGridException;
use AppUtils\Grids\Settings\GridSettings;
use AppUtils\Tests\TestClasses\InMemoryStorage;
use PHPUnit\Framework\TestCase;

class GridSettingsTest extends TestCase
{
    private InMemoryStorage $storage;
    private GridSettings $settings;

    protected function setUp(): void
    {
        $this->storage = new InMemoryStorage();
        $this->settings = new GridSettings('test-grid', $this->storage);
    }

    // AC: getItemsPerPage(?int $default) returns null when nothing is stored and no default given
    public function testGetItemsPerPageReturnsNullWhenNotSet(): void
    {
        $this->assertNull($this->settings->getItemsPerPage());
    }

    // AC: getItemsPerPage(?int $default) returns $default when nothing is stored
    public function testGetItemsPerPageReturnsDefaultWhenNotSet(): void
    {
        $this->assertSame(25, $this->settings->getItemsPerPage(25));
    }

    // AC: setItemsPerPage(int $value) persists the value; getItemsPerPage returns stored value
    public function testSetAndGetItemsPerPage(): void
    {
        $this->settings->setItemsPerPage(50);

        $this->assertSame(50, $this->settings->getItemsPerPage());
    }

    // AC: setItemsPerPage returns $this (fluent)
    public function testSetItemsPerPageIsFluent(): void
    {
        $result = $this->settings->setItemsPerPage(10);

        $this->assertSame($this->settings, $result);
    }

    // AC: stored value takes precedence over $default
    public function testStoredValueOverridesDefault(): void
    {
        $this->settings->setItemsPerPage(100);

        $this->assertSame(100, $this->settings->getItemsPerPage(25));
    }

    // AC: setItemsPerPage(0) throws DataGridException
    public function testSetItemsPerPageZeroThrows(): void
    {
        $this->expectException(DataGridException::class);
        $this->expectExceptionCode(DataGridException::ERROR_INVALID_ITEMS_PER_PAGE);
        $this->settings->setItemsPerPage(0);
    }

    // AC: setItemsPerPage(-1) throws DataGridException
    public function testSetItemsPerPageNegativeThrows(): void
    {
        $this->expectException(DataGridException::class);
        $this->expectExceptionCode(DataGridException::ERROR_INVALID_ITEMS_PER_PAGE);
        $this->settings->setItemsPerPage(-1);
    }

    // Edge-case: storage is isolated per gridID
    public function testStorageIsIsolatedPerGridID(): void
    {
        $otherSettings = new GridSettings('other-grid', $this->storage);
        $this->settings->setItemsPerPage(10);
        $otherSettings->setItemsPerPage(20);

        $this->assertSame(10, $this->settings->getItemsPerPage());
        $this->assertSame(20, $otherSettings->getItemsPerPage());
    }
}
