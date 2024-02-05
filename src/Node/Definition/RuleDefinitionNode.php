<?php

declare(strict_types=1);

namespace Phplrt\Compiler\Node\Definition;

use Phplrt\Compiler\Node\Statement\Statement;
use Phplrt\Compiler\Node\Statement\LanguageInjection;

/**
 * @internal This is an internal class, please do not use it in your application code.
 * @psalm-internal Phplrt\Compiler
 * @psalm-suppress PropertyNotSetInConstructor
 */
class RuleDefinitionNode extends Definition
{
    /**
     * @var non-empty-string
     */
    public string $name;

    /**
     * @param non-empty-string $name
     */
    public function __construct(string $name, public LanguageInjection $delegate, public Statement $body, public bool $keep = true)
    {
        assert($name !== '', 'Rule name must not be empty');

        $this->name = $name;
    }

    public function getIterator(): \Traversable
    {
        yield 'body' => $this->body;
    }
}
