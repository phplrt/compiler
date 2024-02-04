<?php

declare(strict_types=1);

namespace Phplrt\Compiler;

use Phplrt\Compiler\Compiler\CompilerContext;
use Phplrt\Compiler\Generator\CodeGeneratorInterface;
use Phplrt\Compiler\Generator\GeneratableInterface;

interface CompilerInterface extends GeneratableInterface
{
    /**
     * Loads a custom grammar source into the compiler.
     *
     * @return $this
     */
    public function load(mixed $source): self;

    /**
     * Returns loaded context.
     */
    public function getContext(): CompilerContext;

    /**
     * Builds grammar and creates a code generator.
     */
    public function build(): CodeGeneratorInterface;
}
