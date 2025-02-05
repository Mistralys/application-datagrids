<?php

declare(strict_types=1);

namespace AppUtils\Grids\Traits;

use AppUtils\Grids\Columns\GridColumnException;

trait AlignTrait
{
    private ?string $align = null;

    public function getAlign(): ?string
    {
        return $this->align;
    }

    /**
     * @return $this
     */
    public function alignLeft() : self
    {
        $this->align = AlignInterface::ALIGN_LEFT;
        return $this;
    }

    /**
     * @return $this
     */
    public function alignCenter() : self
    {
        $this->align = AlignInterface::ALIGN_CENTER;
        return $this;
    }

    /**
     * @return $this
     */
    public function alignRight() : self
    {
        $this->align = AlignInterface::ALIGN_RIGHT;
        return $this;
    }

    /**
     * @param string|NULL $align
     * @return $this
     * @throws GridColumnException {@see GridColumnException::INVALID_ALIGN_VALUE}
     */
    public function setAlign(?string $align): self
    {
        if($align === null) {
            $this->align = null;
            return $this;
        }

        if(in_array($align, AlignInterface::ALIGNS, true)) {
            $this->align = $align;
            return $this;
        }

        throw new GridColumnException(
            'Invalid data grid column align value.',
            sprintf(
                'The align value [%s] is not valid. Valid values are: [%s]',
                $align,
                implode(', ', AlignInterface::ALIGNS)
            ),
            GridColumnException::INVALID_ALIGN_VALUE
        );
    }
}
