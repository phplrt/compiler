<?php

declare(strict_types=1);

namespace Phplrt\Compiler\Runtime;

use Phplrt\Compiler\Compiler\CompilerContext;
use Phplrt\Contracts\Lexer\LexerInterface;
use Phplrt\Contracts\Parser\ParserInterface;
use Phplrt\Lexer\Lexer;
use Phplrt\Lexer\Multistate;
use Phplrt\Parser\Parser;
use Phplrt\Parser\ParserConfigsInterface;

final class SimpleParser implements ParserInterface
{
    private readonly LexerInterface $lexer;

    private readonly ParserInterface $parser;

    public function __construct(
        private readonly CompilerContext $context,
    ) {
        $this->lexer = $this->createLexer();
        $this->parser = $this->createParser();
    }

    private function createParser(): ParserInterface
    {
        return new Parser($this->lexer, $this->context->rules, [
            ParserConfigsInterface::CONFIG_INITIAL_RULE => $this->context->initial,
            ParserConfigsInterface::CONFIG_AST_BUILDER  => new PrintableNodeBuilder(),
        ]);
    }

    private function createLexer(): LexerInterface
    {
        if (\count($this->context->tokens) === 1) {
            return new Lexer(
                tokens: $this->context->tokens[CompilerContext::STATE_DEFAULT],
                skip: $this->context->skip,
            );
        }

        $states = [];

        foreach ($this->context->tokens as $state => $tokens) {
            $states[$state] = new Lexer($tokens, $this->context->skip);
        }

        return new Multistate($states, $this->context->transitions);
    }

    public function parse(mixed $source): iterable
    {
        return $this->parser->parse($source);
    }
}
