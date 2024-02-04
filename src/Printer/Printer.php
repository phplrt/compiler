<?php

declare(strict_types=1);

namespace Phplrt\Compiler\Printer;

abstract class Printer implements PrinterInterface
{
    public function __construct(
        protected readonly Style $style = new Style(),
    ) {}

    public function getStyle(): Style
    {
        return $this->style;
    }
}
