<?php

declare(strict_types=1);

namespace AppUtils\Grids\Rows\Types;

use AppUtils\Grids\Rows\BaseGridRow;
use AppUtils\Interfaces\StringableInterface;

class MergedRow extends BaseGridRow
{
    private string $content = '';

    public function __construct(string|StringableInterface|NULL $content=null)
    {
        $this->setContent($content);
    }

    public function setContent(string|StringableInterface|NULL $content) : self
    {
        $this->content = (string)$content;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
