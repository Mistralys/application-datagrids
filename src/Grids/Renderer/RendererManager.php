<?php

declare(strict_types=1);

namespace AppUtils\Grids\Renderer;

use AppUtils\ClassHelper;
use AppUtils\Grids\DataGrid;
use AppUtils\Grids\Renderer\Types\Bootstrap5Renderer;
use AppUtils\Grids\Renderer\Types\DefaultRenderer;

class RendererManager
{
    private DataGrid $grid;
    private ?GridRendererInterface $renderer = null;

    public function __construct(DataGrid $grid)
    {
        $this->grid = $grid;
    }

    public function selectDefault() : DefaultRenderer
    {
        return ClassHelper::requireObjectInstanceOf(
            DefaultRenderer::class,
            $this->selectByClass(DefaultRenderer::class)
        );
    }

    public function selectBootstrap5() : Bootstrap5Renderer
    {
        return ClassHelper::requireObjectInstanceOf(
            Bootstrap5Renderer::class,
            $this->selectByClass(Bootstrap5Renderer::class)
        );
    }

    /**
     * @param class-string<GridRendererInterface> $class
     * @return GridRendererInterface
     */
    public function selectByClass(string $class) : GridRendererInterface
    {
        if($this->renderer instanceof $class) {
            return $this->renderer;
        }

        $renderer = new $class($this->grid);

        $this->selectRenderer($renderer);

        return $renderer;
    }

    public function selectRenderer(GridRendererInterface $renderer) : self
    {
        $this->renderer = $renderer;

        return $this;
    }

    public function getRenderer(): GridRendererInterface
    {
        if($this->renderer === null) {
            $this->selectDefault();
        }

        return $this->renderer;
    }
}
