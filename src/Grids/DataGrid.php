<?php

declare(strict_types=1);

namespace AppUtils\Grids;

use AppUtils\Grids\Columns\ColumnManager;
use AppUtils\Grids\Columns\GridColumnInterface;
use AppUtils\Grids\Footer\GridFooter;
use AppUtils\Grids\Form\GridForm;
use AppUtils\Grids\Header\GridHeader;
use AppUtils\Grids\Options\GridOptions;
use AppUtils\Grids\Renderer\GridRendererInterface;
use AppUtils\Grids\Renderer\RendererManager;
use AppUtils\Grids\Rows\GridRowInterface;
use AppUtils\Grids\Rows\RowManager;
use AppUtils\Grids\Rows\Types\MergedRow;
use AppUtils\Traits\ClassableTrait;
use AppUtils\Traits\RenderableBufferedTrait;

class DataGrid implements DataGridInterface
{
    use RenderableBufferedTrait;
    use ClassableTrait;

    protected ColumnManager $columns;
    protected RowManager $rows;
    protected GridOptions $options;
    private int $instanceID;
    private static int $instanceCounter = 0;
    private string $id;
    private GridHeader $header;
    private GridFooter $footer;
    private GridForm $form;

    public function __construct(?string $id=null)
    {
        self::$instanceCounter++;
        $this->instanceID = self::$instanceCounter;

        if(empty($id)) {
            $id = 'grid-'.$this->instanceID;
        }

        $this->id = $id;
        $this->columns = new ColumnManager();
        $this->rows = new RowManager($this);
        $this->options = new GridOptions();
        $this->header = new GridHeader();
        $this->footer = new GridFooter();
        $this->form = new GridForm();
        $this->rendererManager = new RendererManager($this);
    }

    public static function create(?string $id=null) : static
    {
        return new static($id);
    }

    public function getID(): string
    {
        return $this->id;
    }

    protected function generateOutput(): void
    {
        $renderer = $this->renderer()->getRenderer();

        $rows = $this->resolveRows();
        $headerRow = $this->rows->getHeaderRow();
        $columns = $this->columns->getColumns();

        echo $renderer->renderGridFormTop($this->form);
        echo $renderer->renderGridTop();

        echo $renderer->renderHeaderTop($this->header);
        if($headerRow !== null) {
            echo $renderer->renderHeaderRow($headerRow, $columns);
        }
        echo $renderer->renderHeaderBottom($this->header);

        echo $renderer->renderFooterTop($this->footer);
        if($headerRow !== null && $this->options->isHeaderRepeated(count($rows))) {
            echo $renderer->renderHeaderRowRepeated($headerRow, $columns);
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

    public function getSortColumn() : ?GridColumnInterface
    {

    }

    public function getSortDir() : string
    {

    }
}
