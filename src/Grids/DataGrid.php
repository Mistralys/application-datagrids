<?php

declare(strict_types=1);

namespace AppUtils\Grids;

use AppUtils\Grids\Actions\GridActions;
use AppUtils\Grids\Columns\ColumnManager;
use AppUtils\Grids\Columns\GridColumnInterface;
use AppUtils\Grids\Footer\GridFooter;
use AppUtils\Grids\Form\GridForm;
use AppUtils\Grids\Header\GridHeader;
use AppUtils\Grids\Options\GridOptions;
use AppUtils\Grids\Pagination\GridPagination;
use AppUtils\Grids\Renderer\RendererManager;
use AppUtils\Grids\Rows\GridRowInterface;
use AppUtils\Grids\Rows\RowManager;
use AppUtils\Grids\Rows\Types\MergedRow;
use AppUtils\Grids\Settings\GridSettings;
use AppUtils\Grids\Sorting\SortManagerInterface;
use AppUtils\Grids\Storage\GridStorageInterface;
use AppUtils\Traits\ClassableTrait;
use AppUtils\Traits\RenderableBufferedTrait;

class DataGrid implements DataGridInterface
{
    use RenderableBufferedTrait;
    use ClassableTrait;

    protected ColumnManager $columns;
    protected RowManager $rows;
    protected GridOptions $options;
    private string $id;
    private GridStorageInterface $storage;
    private ?GridSettings $settings = null;
    private GridHeader $header;
    private GridFooter $footer;
    private GridForm $form;
    private RendererManager $rendererManager;

    public function __construct(string $id, GridStorageInterface $storage)
    {
        if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $id) !== 1) {
            throw new DataGridException(
                sprintf('Invalid grid ID [%s]: must be a non-empty kebab-case string.', $id),
                null,
                DataGridException::ERROR_INVALID_GRID_ID
            );
        }

        $this->id = $id;
        $this->storage = $storage;
        $this->columns = new ColumnManager();
        $this->rows = new RowManager($this);
        $this->options = new GridOptions();
        $this->header = new GridHeader();
        $this->footer = new GridFooter();
        $this->form = new GridForm();
        $this->rendererManager = new RendererManager($this);
    }

    public static function create(string $id, GridStorageInterface $storage) : static
    {
        // @phpstan-ignore new.static
        return new static($id, $storage);
    }

    public function getStorage(): GridStorageInterface
    {
        return $this->storage;
    }

    public function settings(): GridSettings
    {
        if ($this->settings === null) {
            $this->settings = new GridSettings($this->id, $this->storage);
        }

        return $this->settings;
    }

    public function getID(): string
    {
        return $this->id;
    }

    protected function generateOutput(): void
    {
        $renderer = $this->renderer()->getRenderer();

        if (!$this->actionsProcessed && isset($this->actions)) {
            $this->actions->processSubmittedActions();
            $this->actionsProcessed = true;
        }

        $rows = $this->resolveRows();

        if (isset($this->sortManager)) {
            $this->sortManager->sortRows($rows);
        }

        $headerRow = $this->rows->getHeaderRow();
        $columns = $this->columns->getColumns();

        echo $renderer->renderGridFormTop($this->form);
        echo $renderer->renderGridTop();

        echo $renderer->renderHeaderTop($this->header);
        if($headerRow !== null) {
            echo $renderer->renderHeaderRow($headerRow, $columns);
        }
        if (isset($this->pagination) && $this->pagination->hasProvider() && $this->pagination->isShowAtTop()) {
            echo $renderer->renderPaginationRow($this->pagination);
        }
        echo $renderer->renderHeaderBottom($this->header);

        echo $renderer->renderFooterTop($this->footer);
        if($headerRow !== null && $this->options->isHeaderRepeated(count($rows))) {
            echo $renderer->renderHeaderRowRepeated($headerRow, $columns);
        }

        if (isset($this->pagination) && $this->pagination->hasProvider()) {
            echo $renderer->renderPaginationRow($this->pagination);
        }

        if(isset($this->actions)) {
            echo $renderer->renderActionsRow($this->actions);
        }

        echo $renderer->renderFooterBottom($this->footer);

        echo $renderer->renderBody($rows, $columns);
        echo $renderer->renderGridBottom();
        echo $renderer->renderGridFormBottom($this->form);
    }

    /**
     * @return GridRowInterface[]
     */
    private function resolveRows() : array
    {
        $rows = $this->rows->getRows();

        if(empty($rows)) {
            $rows = array(new MergedRow($this->options->getEmptyMessage()));
        }

        return $rows;
    }

    public function options() : GridOptions
    {
        return $this->options;
    }

    public function columns(): ColumnManager
    {
        return $this->columns;
    }

    public function rows(): RowManager
    {
        return $this->rows;
    }

    public function footer() : GridFooter
    {
        return $this->footer;
    }

    public function header() : GridHeader
    {
        return $this->header;
    }

    public function form() : GridForm
    {
        return $this->form;
    }

    public function renderer() : RendererManager
    {
        return $this->rendererManager;
    }

    private ?GridActions $actions = null;
    private ?GridPagination $pagination = null;
    private ?SortManagerInterface $sortManager = null;
    private bool $actionsProcessed = false;

    public function actions() : GridActions
    {
        if(!isset($this->actions)) {
            $this->actions = new GridActions($this);
        }

        return $this->actions;
    }

    /**
     * Checks whether any actions have been configured on this grid.
     *
     * Unlike {@see actions()}, this method does NOT lazily instantiate
     * the GridActions object — it returns false when no actions have
     * been registered.
     */
    public function hasActions(): bool
    {
        return isset($this->actions) && $this->actions->hasActions();
    }

    /**
     * Explicitly processes submitted action forms.
     *
     * Call this before rendering to allow action callbacks to perform
     * request-lifecycle operations (redirects, session writes, etc.)
     * before any HTML output is flushed.
     *
     * This method is idempotent: calling it multiple times (or calling it
     * before rendering, where generateOutput() also triggers processing)
     * will only invoke the action callback once.
     *
     * @return bool True when a matching action was dispatched, false otherwise.
     */
    public function processActions(): bool
    {
        if ($this->actionsProcessed) {
            return false;
        }

        $this->actionsProcessed = true;

        if (!isset($this->actions)) {
            return false;
        }

        return $this->actions->processSubmittedActions();
    }

    public function pagination(): GridPagination
    {
        if (!isset($this->pagination)) {
            $this->pagination = new GridPagination($this);
        }

        return $this->pagination;
    }

    public function sorting(): SortManagerInterface
    {
        if (!isset($this->sortManager)) {
            $this->sortManager = new \AppUtils\Grids\Sorting\SortManager($this);
        }

        return $this->sortManager;
    }

    public function getSortColumn(): ?GridColumnInterface
    {
        return $this->sorting()->getSortColumn();
    }

    public function getSortDir(): string
    {
        return $this->sorting()->getSortDir();
    }
}
