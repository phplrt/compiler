<?php

declare(strict_types=1);

namespace Phplrt\Compiler;

use Phplrt\Compiler\Ast\Node;
use Phplrt\Compiler\Context\CompilerContext;
use Phplrt\Compiler\Context\IdCollection;
use Phplrt\Compiler\Exception\GrammarException;
use Phplrt\Compiler\Generator\CodeGeneratorInterface;
use Phplrt\Compiler\Generator\PhpCodeGenerator;
use Phplrt\Compiler\Grammar\GrammarInterface;
use Phplrt\Compiler\Grammar\PP2Grammar;
use Phplrt\Compiler\Runtime\PrintableNodeBuilder;
use Phplrt\Contracts\Ast\NodeInterface;
use Phplrt\Contracts\Exception\RuntimeExceptionInterface;
use Phplrt\Contracts\Lexer\LexerInterface;
use Phplrt\Contracts\Parser\ParserInterface;
use Phplrt\Contracts\Source\ReadableInterface;
use Phplrt\Lexer\Lexer;
use Phplrt\Lexer\Multistate;
use Phplrt\Parser\Parser;
use Phplrt\Parser\ParserConfigsInterface;
use Phplrt\Source\File;
use Phplrt\Visitor\Traverser;
use Phplrt\Visitor\TraverserInterface;

/**
 * @template-implements ParserInterface<Node>
 */
class Compiler implements CompilerInterface, ParserInterface, \Stringable
{
    private readonly GrammarInterface $grammar;

    private readonly CompilerContext $analyzer;

    private readonly TraverserInterface $preloader;

    public function __construct(?GrammarInterface $grammar = null)
    {
        $this->grammar = $grammar ?? new PP2Grammar();

        $this->preloader = $this->bootPreloader($ids = new IdCollection());
        $this->analyzer = new CompilerContext($ids);
    }

    private function bootPreloader(IdCollection $ids): TraverserInterface
    {
        return (new Traverser())
            ->with(new IncludesExecutor(fn(string $pathname): iterable => $this->run(File::fromPathname($pathname))))
            ->with($ids);
    }

    /**
     * @return iterable<Node>
     * @throws \Throwable
     */
    private function run(ReadableInterface $source): iterable
    {
        try {
            $ast = $this->grammar->parse($source);

            return $this->preloader->traverse($ast);
        } catch (GrammarException $e) {
            throw $e;
        } catch (RuntimeExceptionInterface $e) {
            throw new GrammarException($e->getMessage(), $source, $e->getToken()->getOffset());
        }
    }

    /**
     * @throws \Throwable
     */
    public function parse(mixed $source): iterable
    {
        $lexer = $this->createLexer();

        $parser = new Parser($lexer, $this->analyzer->rules, [
            ParserConfigsInterface::CONFIG_INITIAL_RULE => $this->analyzer->initial,
            ParserConfigsInterface::CONFIG_AST_BUILDER => new PrintableNodeBuilder(),
        ]);

        return $parser->parse($source);
    }

    private function createLexer(): LexerInterface
    {
        if (\count($this->analyzer->tokens) === 1) {
            return new Lexer($this->analyzer->tokens[CompilerContext::STATE_DEFAULT], $this->analyzer->skip);
        }

        $states = [];

        foreach ($this->analyzer->tokens as $state => $tokens) {
            $states[$state] = new Lexer($tokens, $this->analyzer->skip);
        }

        return new Multistate($states, $this->analyzer->transitions);
    }

    public function load(mixed $source): self
    {
        /** @var iterable<NodeInterface> $ast */
        $ast = $this->run(File::new($source));

        (new Traverser())
            ->with($this->analyzer)
            ->traverse($ast);

        return $this;
    }

    /**
     * @deprecated since phplrt 3.6 and will be removed in 4.0. Please
     *             use {@see getContext()} instead.
     */
    public function getAnalyzer(): CompilerContext
    {
        trigger_deprecation('phplrt/compiler', '3.6', <<<'MSG'
            Using "%s::getAnalyzer()" is deprecated, please use "%1$s::getContext()" instead.
            MSG, static::class);

        return $this->analyzer;
    }

    public function getContext(): CompilerContext
    {
        return $this->analyzer;
    }

    public function build(): CodeGeneratorInterface
    {
        return new PhpCodeGenerator($this->analyzer);
    }

    public function __toString(): string
    {
        $generator = $this->build();

        return $generator->generate();
    }
}
