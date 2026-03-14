<?php

declare(strict_types=1);

namespace AppUtils\Tests\Sorting;

use AppUtils\Grids\DataGrid;
use AppUtils\Grids\DataGridInterface;
use AppUtils\Grids\Renderer\Types\Bootstrap5Renderer;
use PHPUnit\Framework\TestCase;

/**
 * Tests for sort-aware header cell rendering.
 * Covers base and Bootstrap5 renderers for sortable/non-sortable/active-sort scenarios.
 */
class RendererSortHeaderTest extends TestCase
{
    /**
     * @var array<string,mixed>
     */
    private array $originalGet = [];
    private string|null $originalRequestUri = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalGet = $_GET;
        $this->originalRequestUri = $_SERVER['REQUEST_URI'] ?? null;
    }

    protected function tearDown(): void
    {
        $_GET = $this->originalGet;
        if ($this->originalRequestUri !== null) {
            $_SERVER['REQUEST_URI'] = $this->originalRequestUri;
        } else {
            unset($_SERVER['REQUEST_URI']);
        }
        parent::tearDown();
    }

    // =========================================================================
    // Base renderer
    // =========================================================================

    public function test_nonSortableColumn_noLink(): void
    {
        $_GET = [];
        $_SERVER['REQUEST_URI'] = '/grid';

        $grid = DataGrid::create();
        $grid->columns()->add('name', 'Name');
        $grid->renderer()->selectDefault();

        $column = $grid->columns()->getByName('name');
        $renderer = $grid->renderer()->getRenderer();

        $html = (string)$renderer->renderHeaderCell($column);

        $this->assertStringContainsString('<th', $html);
        $this->assertStringContainsString('Name', $html);
        $this->assertStringNotContainsString('<a', $html);
    }

    public function test_sortableColumn_hasLink(): void
    {
        $_GET = [];
        $_SERVER['REQUEST_URI'] = '/grid';

        $grid = DataGrid::create();
        $grid->columns()->add('name', 'Name')->useNativeSorting();
        $grid->renderer()->selectDefault();

        $column = $grid->columns()->getByName('name');
        $renderer = $grid->renderer()->getRenderer();

        $html = (string)$renderer->renderHeaderCell($column);

        $this->assertStringContainsString('<th', $html);
        $this->assertStringContainsString('<a ', $html);
        $this->assertStringContainsString('sort=name', $html);
    }

    public function test_activeSortColumn_hasIndicator(): void
    {
        $_GET['sort'] = 'name';
        $_GET['sort_dir'] = DataGridInterface::SORT_ASC;
        $_SERVER['REQUEST_URI'] = '/grid?sort=name&sort_dir=ASC';

        $grid = DataGrid::create();
        $grid->columns()->add('name', 'Name')->useNativeSorting();
        $grid->renderer()->selectDefault();

        $column = $grid->columns()->getByName('name');
        $renderer = $grid->renderer()->getRenderer();

        $html = (string)$renderer->renderHeaderCell($column);

        // Active sort column's <a> must contain a direction indicator (▲ or ▼)
        $this->assertTrue(
            str_contains($html, '▲') || str_contains($html, '▼'),
            'Expected sort direction indicator (▲ or ▼) to be present in header cell HTML'
        );
    }

    // =========================================================================
    // Bootstrap5 renderer
    // =========================================================================

    public function test_bootstrap5_nonSortable_noLink(): void
    {
        $_GET = [];
        $_SERVER['REQUEST_URI'] = '/grid';

        $grid = DataGrid::create();
        $grid->columns()->add('name', 'Name');
        $bs5 = $grid->renderer()->selectBootstrap5();

        $column = $grid->columns()->getByName('name');

        $html = (string)$bs5->renderHeaderCell($column);

        $this->assertStringContainsString('<th', $html);
        $this->assertStringContainsString('Name', $html);
        $this->assertStringNotContainsString('<a', $html);
    }

    public function test_bootstrap5_sortableColumn_hasBootstrapClasses(): void
    {
        $_GET = [];
        $_SERVER['REQUEST_URI'] = '/grid';

        $grid = DataGrid::create();
        $grid->columns()->add('name', 'Name')->useNativeSorting();
        $bs5 = $grid->renderer()->selectBootstrap5();

        $column = $grid->columns()->getByName('name');

        $html = (string)$bs5->renderHeaderCell($column);

        $this->assertStringContainsString('<a ', $html);
        $this->assertStringContainsString('text-decoration-none', $html);
        $this->assertStringContainsString('text-reset', $html);
        $this->assertStringContainsString('d-inline-flex', $html);
        $this->assertStringContainsString('align-items-center', $html);
        $this->assertStringContainsString('gap-1', $html);
    }
}
