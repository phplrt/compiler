<?php

declare(strict_types=1);

namespace Phplrt\Compiler\Node\Statement;

/**
 * @internal This is an internal class, please do not use it in your application code.
 * @psalm-internal Phplrt\Compiler
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class ClassDelegate extends LanguageInjection
{
    /**
     * @param class-string $class
     */
    public function __construct(string $class)
    {
        assert($class !== '', 'Class name must not be empty');

        parent::__construct(\vsprintf('return new \\%s($state, $children, $offset);', [
            \ltrim($class, '\\')
        ]));
    }
}
