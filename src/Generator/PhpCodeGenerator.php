<?php

declare(strict_types=1);

namespace Phplrt\Compiler\Generator;

use Phplrt\Compiler\Compiler\CompilerContext;
use Phplrt\Compiler\Printer\PhpPrinter;
use Phplrt\Compiler\Printer\PrintableValueInterface;
use Phplrt\Compiler\Printer\PrinterInterface;
use Phplrt\Compiler\Printer\Value\PhpClassReference;
use Phplrt\Compiler\Printer\Value\PhpConstReference;
use Phplrt\Compiler\Printer\Value\PhpFunctionReference;
use Phplrt\Compiler\Printer\Value\PhpLanguageInjection;
use Phplrt\Compiler\Printer\Value\PhpRuleInstantiation;
use Phplrt\Parser\Grammar\RuleInterface;

final class PhpCodeGenerator extends CodeGenerator
{
    private bool $strict = true;

    public function __construct(
        CompilerContext $analyzer,
        private readonly PrinterInterface $printer = new PhpPrinter(),
    ) {
        parent::__construct($analyzer);
    }

    public function withStrictTypes(): self
    {
        $self = clone $this;
        $self->strict = true;

        return $self;
    }

    public function withoutStrictTypes(): self
    {
        $self = clone $this;
        $self->strict = false;

        return $self;
    }

    /**
     * @deprecated Since 4.0, please use immutable {@see CodeGeneratorInterface::withClassReference()} method instead.
     *
     * @param non-empty-string $class
     * @param non-empty-string|null $alias
     */
    public function withClassUsage(string $class, string $alias = null): self
    {
        $this->classes[$class] = $alias;

        return $this;
    }

    public function generate(): string
    {
        $style = $this->printer->getStyle();

        return \implode($style->lineDelimiter, \array_filter([
            '<?php',
            $this->getStrictTypesResult(),
            $this->getReferencesResult(),
            $this->getBodyResult(),
        ]));
    }

    private function getStrictTypesResult(): string
    {
        $style = $this->printer->getStyle();

        if ($this->strict) {
            return $style->lineDelimiter
                . 'declare(strict_types=1);';
        }

        return '';
    }

    private function getReferencesResult(): string
    {
        $result = [];

        foreach ($this->getReferences() as $reference) {
            $result[] = $this->printer->print($reference);
        }

        if ($result === []) {
            return '';
        }

        $style = $this->printer->getStyle();

        return $style->lineDelimiter
            . \implode($style->lineDelimiter, $result);
    }

    private function getTemplateResult(): string
    {
        $style = $this->printer->getStyle();

        return $style->lineDelimiter . <<<'PHP'
            /**
             * @var array{
             *     initial: int<0, max>|non-empty-string,
             *     tokens: array{
             *         default: array<non-empty-string, non-empty-string>
             *     },
             *     skip: array<non-empty-string>,
             *     grammar: array<int<0, max>|non-empty-string, \Phplrt\Parser\Grammar\RuleInterface>,
             *     reducers: array<int<0, max>|non-empty-string, callable(\Phplrt\Parser\Context, mixed):mixed>,
             *     transitions?: array
             * }
             */
            PHP;
    }

    private function getBodyResult(): string
    {
        $style = $this->printer->getStyle();

        $result = [
            'initial' => $this->analyzer->initial,
            'tokens' => $this->analyzer->tokens,
            'skip' => $this->analyzer->skip,
            'grammar' => $this->getOrderedGrammar(),
            'reducers' => $this->getOrderedReducers(),
        ];

        if ($this->analyzer->transitions !== []) {
            $result['transitions'] = $this->analyzer->transitions;
        } else {
            $result['tokens'] = $this->analyzer->tokens[CompilerContext::STATE_DEFAULT];
        }

        return $style->lineDelimiter
            . \sprintf('return %s;', $this->printer->print($result));
    }

    private function getOrderedGrammar(): array
    {
        $grammar = $this->getGrammar();

        \ksort($grammar);

        return $grammar;
    }

    private function getGrammar(): array
    {
        $map = static fn(RuleInterface $rule): PrintableValueInterface
            => new PhpRuleInstantiation($rule);

        return \array_map($map, $this->analyzer->rules);
    }

    private function getOrderedReducers(): array
    {
        $result = $this->getReducers();

        \ksort($result);

        return $result;
    }

    private function getReducers(): array
    {
        $map = static fn(string $code): PrintableValueInterface
            => new PhpLanguageInjection($code);

        return \array_map($map, $this->analyzer->reducers);
    }

    private function getReferences(): array
    {
        $result = [];

        foreach ($this->classes as $class => $alias) {
            $result[] = new PhpClassReference($class, $alias);
        }

        foreach ($this->functions as $function => $alias) {
            $result[] = new PhpFunctionReference($function, $alias);
        }

        foreach ($this->constants as $const => $alias) {
            $result[] = new PhpConstReference($const, $alias);
        }

        return $result;
    }
}
