<?php

declare(strict_types=1);

namespace Phplrt\Compiler\Grammar;

use Phplrt\Contracts\Source\ReadableInterface;
use Phplrt\Lexer\Token\Composite;
use Phplrt\Contracts\Lexer\LexerInterface;
use Phplrt\Contracts\Lexer\TokenInterface;
use Phplrt\Source\Exception\NotAccessibleException;

class PP2PHPLexer implements LexerInterface
{
    public function __construct(
        private readonly PhpLexer $lexer,
    ) {}

    /**
     * @param resource|string|ReadableInterface $source
     * @param int<0, max> $offset
     * @return iterable<TokenInterface>
     * @throws NotAccessibleException
     * @throws \RuntimeException
     */
    public function lex($source, int $offset = 0): iterable
    {
        $depth = 0;

        $children = [];
        $value  = '';

        foreach ($this->lexer->lex($source, $offset) as $inner) {
            if ($inner->getName() === '{') {
                ++$depth;
            }

            if ($inner->getName() === '}') {
                /** @psalm-suppress PossiblyInvalidPropertyAssignmentValue */
                --$depth;
            }

            if ($depth < 0) {
                break;
            }

            $children[] = $inner;
            $value .= $inner->getValue();
        }

        yield new Composite('T_PHP_CODE', $value, $offset, $children);
    }
}
