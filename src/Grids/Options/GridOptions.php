<?php

declare(strict_types=1);

namespace AppUtils\Grids\Options;

use AppUtils\Interfaces\StringableInterface;
use function AppUtils\t;

class GridOptions
{
    private string $emptyMessage = '';
    private bool $repeatHeader = false;
    private int $repeatHeaderThreshold = 10;
    private bool $headerRow = true;

    public function setEmptyMessage(string|StringableInterface $message) : self
    {
        $this->emptyMessage = (string)$message;
        return $this;
    }

    public function setRepeatHeader(bool $enabled, ?int $threshold = null) : self
    {
        $this->repeatHeader = $enabled;

        if($threshold !== null) {
            $this->repeatHeaderThreshold = $threshold;
        }

        return $this;
    }

    public function getEmptyMessage() : string
    {
        if(!empty($this->emptyMessage)) {
            return $this->emptyMessage;
        }

        return t('No items available.');
    }

    public function isHeaderRepeated(int $count) : bool
    {
        return $this->repeatHeader && $count >= $this->repeatHeaderThreshold;
    }

    public function isHeaderRowEnabled() : bool
    {
        return $this->headerRow;
    }

    public function setHeaderRowEnabled(bool $enabled) : self
    {
        $this->headerRow = $enabled;
        return $this;
    }
}
