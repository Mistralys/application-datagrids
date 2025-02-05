<?php

declare(strict_types=1);

namespace AppUtils\Grids\Traits;

interface IDInterface
{
    public function setID(?string $id): self;
    public function getID() : ?string;
    public function requireID() : string;
}