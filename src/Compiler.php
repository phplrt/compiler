<?php

declare(strict_types=1);

namespace Phplrt\Compiler;

use Phplrt\Compiler\Ast\Node;
use Phplrt\Compiler\Compiler\CompilerContext;
use Phplrt\Compiler\Compiler\IdCollection;
use Phplrt\Compiler\Exception\GrammarException;
use Phplrt\Compiler\Generator\PhpCodeGenerator;
use Phplrt\Compiler\Grammar\GrammarInterface;
use Phplrt\Compiler\Grammar\PP2Grammar;
use Phplrt\Compiler\Runtime\SimpleParser;
use Phplrt\Contracts\Ast\NodeInterface;
use Phplrt\Contracts\Exception\RuntimeExceptionInterface;
use Phplrt\Contracts\Parser\ParserInterface;
use Phplrt\Contracts\Source\ReadableInterface;
use Phplrt\Contracts\Source\SourceFactoryInterface;
use Phplrt\Source\SourceFactory;
use Phplrt\Visitor\Traverser;
use Phplrt\Visitor\TraverserInterface;

class Compiler implements ParserInterface, \Stringable
{
    private readonly IdCollection $ids;

    private readonly CompilerContext $context;

    private readonly TraverserInterface $preloader;

    public function __construct(
        private readonly GrammarInterface $grammar = new PP2Grammar(),
        private readonly SourceFactoryInterface $sources = new SourceFactory(),
    ) {
        $this->ids = new IdCollection();

        $this->preloader = $this->bootPreloader($this->ids);
        $this->context = new CompilerContext($this->ids);
    }

    private function bootPreloader(IdCollection $ids): TraverserInterface
    {
        return (new Traverser())
            ->with(new IncludesExecutor(function (string $pathname): iterable {
                return $this->parseGrammar($this->sources->createFromFile($pathname));
            }))
            ->with($ids);
    }

    /**
     * @return iterable<Node>
     * @throws \Throwable
     *
     * @psalm-suppress MoreSpecificReturnType
     * @psalm-suppress LessSpecificReturnStatement
     */
    private function parseGrammar(ReadableInterface $source): iterable
    {
        try {
            $ast = $this->grammar->parse($source);

            return $this->preloader->traverse($ast);
        } catch (GrammarException $e) {
            throw $e;
        } catch (RuntimeExceptionInterface $e) {
            $token = $e->getToken();

            throw new GrammarException($e->getMessage(), $source, $token->getOffset());
        }
    }

    public function parse(mixed $source): iterable
    {
        $parser = new SimpleParser($this->context);

        return $parser->parse($source);
    }

    public function load(mixed $source): self
    {
        /** @var iterable<NodeInterface> $ast */
        $ast = $this->parseGrammar($this->sources->create($source));

        (new Traverser())
            ->with($this->context)
            ->traverse($ast);

        return $this;
    }

    public function getContext(): CompilerContext
    {
        return $this->context;
    }

    public function build(): PhpCodeGenerator
    {
        return new PhpCodeGenerator($this->context);
    }

    public function __toString(): string
    {
        $generator = $this->build();

        return $generator->generate();
    }
}
