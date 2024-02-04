<?php

declare(strict_types=1);

namespace Phplrt\Compiler\Generator;

use Phplrt\Compiler\Compiler\CompilerContext;

abstract class CodeGenerator implements CodeGeneratorInterface
{
    /**
     * @var array<non-empty-string, non-empty-string|null>
     */
    protected array $classes = [];

    /**
     * @var array<non-empty-string, non-empty-string|null>
     */
    protected array $functions = [];

    /**
     * @var array<non-empty-string, non-empty-string|null>
     */
    protected array $constants = [];

    public function __construct(
        protected CompilerContext $analyzer,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function withClassReference(string $class, string $alias = null): CodeGeneratorInterface
    {
        $self = clone $this;
        $self->classes[$class] = $alias;

        return $self;
    }

    /**
     * {@inheritDoc}
     */
    public function withFunctionReference(string $function, string $alias = null): CodeGeneratorInterface
    {
        $self = clone $this;
        $self->functions[$function] = $alias;

        return $self;
    }

    /**
     * {@inheritDoc}
     */
    public function withConstReference(string $const, string $alias = null): CodeGeneratorInterface
    {
        $self = clone $this;
        $self->constants[$const] = $alias;

        return $self;
    }

    public function __toString(): string
    {
        return $this->generate();
    }
}
