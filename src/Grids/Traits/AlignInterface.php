<?php

declare(strict_types=1);

namespace AppUtils\Grids\Traits;

interface AlignInterface
{
    public const ALIGN_CENTER = 'center';
    public const ALIGNS = array(
        AlignInterface::ALIGN_LEFT,
        AlignInterface::ALIGN_CENTER,
        AlignInterface::ALIGN_RIGHT
    );
    public const ALIGN_RIGHT = 'right';
    public const ALIGN_LEFT = 'left';

    public function alignRight() : self;
    public function alignLeft() : self;
    public function alignCenter() : self;
    public function setAlign(?string $align) : self;
    public function getAlign() : ?string;
}
