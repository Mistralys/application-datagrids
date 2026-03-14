<?php

declare(strict_types=1);

namespace AppUtils\Grids\Columns;

use AppUtils\Grids\Traits\AlignTrait;
use AppUtils\Grids\Traits\IDTrait;
use AppUtils\Interfaces\StringableInterface;
use AppUtils\NumberInfo;
use AppUtils\Traits\ClassableTrait;
use function AppUtils\parseNumber;

abstract class BaseGridColumn implements GridColumnInterface
{
    use IDTrait;
    use ClassableTrait;
    use AlignTrait;

    private string $name;
    private string $label;
    private bool $nowrap = false;
    private bool $compact = false;
    private ?SortMode $sortMode = null;
    private ?\Closure $sortCallback = null;
    private ?NumberInfo $width = null;

    /**
     * @param string $name
     * @param string|StringableInterface|NULL $label
     * @throws GridColumnException {@see GridColumnException::INVALID_COLUMN_NAME}
     */
    public function __construct(string $name, string|StringableInterface|NULL $label)
    {
        $this->name = trim($name);
        $this->label = (string)$label;

        $this->validateName();

        $this->init();
    }

    /**
     * @return void
     * @throws GridColumnException {@see GridColumnException::INVALID_COLUMN_NAME}
     */
    private function validateName() : void
    {
        if(!empty($this->name) && preg_match('/^[a-z][a-z0-9\-_]*$/i', $this->name)) {
            return;
        }

        throw new GridColumnException(
            'Invalid data grid column name.',
            sprintf(
                'The column name must be a non-empty string starting with a letter. '.PHP_EOL.
                'Allowed special characters are: [-] and [_]. '.PHP_EOL.
                'The specified name [%s] is not valid.',
                $this->name
            ),
            GridColumnException::INVALID_COLUMN_NAME
        );
    }

    abstract protected function init() : void;

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }


    public function setNowrap(bool $nowrap = true): self
    {
        $this->nowrap = $nowrap;
        return $this;
    }

    public function isNowrap(): bool
    {
        return $this->nowrap;
    }

    public function setCompact(bool $compact = true): self
    {
        $this->compact = $compact;
        return $this;
    }

    public function isCompact(): bool
    {
        return $this->compact;
    }

    public function useNativeSorting(): self
    {
        $this->sortMode = SortMode::Native;
        $this->sortCallback = null;
        return $this;
    }

    public function useCallbackSorting(callable $callback): self
    {
        $this->sortMode = SortMode::Callback;
        $this->sortCallback = \Closure::fromCallable($callback);
        return $this;
    }

    public function useManualSorting(): self
    {
        $this->sortMode = SortMode::Manual;
        $this->sortCallback = null;
        return $this;
    }

    public function isSortable(): bool
    {
        return $this->sortMode !== null;
    }

    public function getSortMode(): ?SortMode
    {
        return $this->sortMode;
    }

    public function getSortCallback(): ?\Closure
    {
        return $this->sortCallback;
    }

    public function setWidth(int|string|NumberInfo|NULL $width): self
    {
        if($width === null) {
            $this->width = null;
        } else {
            $this->width = parseNumber($width, true);
        }

        return $this;
    }

    public function getWidth(): ?NumberInfo
    {
        return $this->width;
    }
}
