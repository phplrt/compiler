<?php

declare(strict_types=1);

namespace Phplrt\Compiler\Renderer;

interface RendererInterface
{
    /**
     * @param int<0, max> $depth
     */
    public function prefix(int $depth): string;

    /**
     * @param int<0, max> $depth
     */
    public function fromPhp(mixed $data, int $depth = 0, bool $multiline = true): string;

    /**
     * @param int<0, max> $depth
     */
    public function fromString(mixed $data, int $depth = 0, bool $multiline = true): string;
}
