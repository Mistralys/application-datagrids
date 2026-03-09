<?php

declare(strict_types=1);

namespace AppUtils\Grids\Actions\Type;

class RegularAction implements GridActionInterface
{
    private string $name;
    private string $label;

    public function __construct(string $name, string $label)
    {
        $this->name = $name;
        $this->label = $label;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }
}
