<?php

declare(strict_types=1);

namespace AppUtils\Grids\Form;

use AppUtils\Grids\Traits\IDInterface;
use AppUtils\Grids\Traits\IDTrait;
use AppUtils\Interfaces\ClassableInterface;
use AppUtils\Traits\ClassableTrait;

class GridForm implements ClassableInterface, IDInterface
{
    use ClassableTrait;
    use IDTrait;

    /**
     * @var array<string,HiddenVar>
     */
    private array $hiddenVars = array();

    /**
     * @param string $name
     * @param string|int|float|bool|NULL $value Set to `null` to remove the hidden variable.
     * @return HiddenVar
     */
    public function addHiddenVar(string $name, string|int|float|bool $value, ?string $id=null): HiddenVar
    {
        $var = new HiddenVar($name, $value, $id);

        $this->registerHiddenVar($var);

        return $var;
    }

    public function registerHiddenVar(HiddenVar $var) : self
    {
        $this->hiddenVars[$var->getName()] = $var;
        return $this;
    }

    /**
     * @param array<string,string|int|bool|NULL> $vars
     * @return $this
     */
    public function setHiddenVars(array $vars) : self
    {
        foreach($vars as $name => $value) {
            $this->addHiddenVar($name, $value);
        }

        return $this;
    }

    /**
     * @return HiddenVar[]
     */
    public function getHiddenVars(): array
    {
        return array_values($this->hiddenVars);
    }
}
