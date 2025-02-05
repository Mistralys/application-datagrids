<?php

declare(strict_types=1);

namespace AppUtils\Grids\Traits;

use AppUtils\JSHelper;

trait IDTrait
{
    private ?string $id = null;

    public function setID(?string $id): self
    {
        if(!empty($id)) {
            $this->id = $id;
        } else {
            $this->id = null;
        }

        return $this;
    }

    public function getID() : ?string
    {
        return $this->id;
    }

    public function requireID() : string
    {
        $id = $this->getID();

        if(empty($id)) {
            $id = JSHelper::nextElementID();
            $this->setID($id);
        }

        return $id;
    }
}