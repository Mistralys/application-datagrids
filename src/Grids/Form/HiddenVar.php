<?php

declare(strict_types=1);

namespace AppUtils\Grids\Form;

use AppUtils\HTMLTag;
use AppUtils\Interfaces\RenderableInterface;
use AppUtils\Traits\RenderableTrait;

class HiddenVar implements RenderableInterface
{
    use RenderableTrait;

    private string $name;
    private string $value = '';
    private ?string $id = null;

    public function __construct(string $name, string|int|float|bool $value, ?string $id = null)
    {
        $this->name = $name;

        $this->setValue($value);
        $this->setID($id);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string|int|float|bool $value) : self
    {
        $this->value = (string)$value;
        return $this;
    }

    public function getID(): ?string
    {
        return $this->id;
    }

    public function setID(?string $id) : self
    {
        $this->id = $id;
        return $this;
    }

    public function render(): string
    {
        return HTMLTag::create('input')
            ->attr('type', 'hidden')
            ->attr('value', $this->value)
            ->id((string)$this->id)
            ->render();
    }
}
