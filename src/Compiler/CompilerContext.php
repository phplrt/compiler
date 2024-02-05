<?php

declare(strict_types=1);

namespace Phplrt\Compiler\Compiler;

use Phplrt\Compiler\Node\Definition\PragmaDefinitionNode;
use Phplrt\Compiler\Node\Definition\RuleDefinitionNode;
use Phplrt\Compiler\Node\Definition\TokenDefinitionNode;
use Phplrt\Compiler\Node\Statement\AlternationNode;
use Phplrt\Compiler\Node\Statement\ConcatenationNode;
use Phplrt\Compiler\Node\Statement\PatternNode;
use Phplrt\Compiler\Node\Statement\RepetitionNode;
use Phplrt\Compiler\Node\Statement\RuleNode;
use Phplrt\Compiler\Node\Statement\Statement;
use Phplrt\Compiler\Node\Statement\TokenNode;
use Phplrt\Compiler\Exception\GrammarException;
use Phplrt\Contracts\Ast\NodeInterface;
use Phplrt\Parser\Exception\ParserRuntimeException;
use Phplrt\Parser\Grammar\Alternation;
use Phplrt\Parser\Grammar\Concatenation;
use Phplrt\Parser\Grammar\Lexeme;
use Phplrt\Parser\Grammar\Optional;
use Phplrt\Parser\Grammar\Repetition;
use Phplrt\Parser\Grammar\RuleInterface;
use Phplrt\Source\Exception\NotAccessibleException;
use Phplrt\Visitor\Visitor;

/**
 * @internal This is an internal library class, please do not use it in your code.
 * @psalm-internal Phplrt\Compiler
 */
class CompilerContext extends Visitor
{
    /**
     * @var non-empty-string
     */
    public const STATE_DEFAULT = 'default';

    /**
     * @var non-empty-string
     */
    public const PRAGMA_ROOT = 'root';

    /**
     * @var list<RuleInterface>
     */
    public array $rules = [];

    /**
     * @var list<string>
     */
    public array $reducers = [];

    /**
     * @var array<non-empty-string, array<non-empty-string, non-empty-string>>
     */
    public array $tokens = [
        self::STATE_DEFAULT => [],
    ];

    /**
     * @var array<non-empty-string, array<non-empty-string, non-empty-string>>
     */
    public array $transitions = [];

    /**
     * @var list<non-empty-string>
     */
    public array $skip = [];

    /**
     * @var non-empty-string|int|null
     */
    public int|string|null $initial = null;

    /**
     * @var int<0, max>
     */
    private int $counter = 0;

    /**
     * @var array<non-empty-string, int<0, max>>
     */
    private array $aliases = [];

    public function __construct(
        private readonly IdCollection $ids,
    ) {}

    /**
     * {@inheritDoc}
     *
     * @psalm-suppress PropertyTypeCoercion
     */
    public function enter(NodeInterface $node): void
    {
        if ($node instanceof TokenDefinitionNode) {
            $state = $node->state ?? self::STATE_DEFAULT;

            if (!\array_key_exists($state, $this->tokens)) {
                $this->tokens[$state] = [];
            }

            $this->tokens[$state][$node->name] = $node->value;

            if ($node->next !== null) {
                $this->transitions[$state][$node->name] = $node->next;
            }

            if (!$node->keep) {
                $this->skip[] = $node->name;
            }
        }

        if ($node instanceof PatternNode) {
            $lexemes = \array_reverse($this->tokens[self::STATE_DEFAULT]);
            $lexemes[$node->name] = $node->pattern;

            $this->tokens[self::STATE_DEFAULT] = \array_reverse($lexemes);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @psalm-suppress PropertyTypeCoercion
     */
    public function leave(NodeInterface $node): void
    {
        if ($node instanceof PragmaDefinitionNode) {
            if ($node->name !== self::PRAGMA_ROOT) {
                $error = 'Unrecognized pragma "%s"';
                throw new GrammarException(\sprintf($error, $node->name), $node->file, $node->offset);
            }

            $this->initial = $this->name($node->value);
        }

        if ($node instanceof RuleDefinitionNode) {
            $id = $this->register($this->rule($node), $node->name);

            if ($node->delegate->code !== null) {
                $this->reducers[$id] = $node->delegate->code;
            }
        }
    }

    /**
     * @param non-empty-string $rule
     * @return non-empty-string|int<0, max>
     */
    private function name(string $rule): string|int
    {
        if ($this->ids->rule($rule) === false) {
            return $this->aliases[$rule] ??= $this->counter++;
        }

        return $rule;
    }

    /**
     * @param non-empty-string|null $name
     * @return non-empty-string|int<0, max>
     */
    private function register(RuleInterface $rule, string $name = null): string|int
    {
        if ($name === null) {
            $this->rules[$this->counter] = $rule;

            \ksort($this->rules);

            return $this->counter++;
        }

        $id = $this->name($name);

        $this->rules[$id] = $rule;

        if ($this->initial === null) {
            $this->initial = $id;
        }

        return $id;
    }

    /**
     * @throws NotAccessibleException
     * @throws ParserRuntimeException
     * @throws \RuntimeException
     */
    private function rule(RuleDefinitionNode $def): RuleInterface
    {
        $rule = $this->reduce($def->body);

        if ($rule instanceof RuleInterface) {
            return $rule;
        }

        return new Concatenation([$rule]);
    }

    /**
     * @return RuleInterface|non-empty-string|int<0, max>
     * @throws NotAccessibleException
     * @throws ParserRuntimeException
     * @throws \RuntimeException
     * @psalm-suppress PossiblyInvalidArgument
     */
    private function reduce(Statement $statement): RuleInterface|string|int
    {
        switch (true) {
            case $statement instanceof AlternationNode:
                return new Alternation($this->loadForAlternation($statement));

            case $statement instanceof RepetitionNode:
                $info = $statement->quantifier;

                if ($info->from === 0 && $info->to === 1) {
                    return new Optional($this->load($statement->statement));
                }

                return new Repetition($this->load($statement->statement), $info->from, $info->to);

            case $statement instanceof ConcatenationNode:
                return new Concatenation($this->load($statement->statements));

            case $statement instanceof PatternNode:
                return new Lexeme($statement->name, false);

            case $statement instanceof TokenNode:
                return $this->tokenRelation($statement);

            case $statement instanceof RuleNode:
                return $this->ruleRelation($statement);

            default:
                $error = \sprintf('Unsupported statement %s', $statement::class);

                throw new GrammarException($error, $statement->file, $statement->offset);
        }
    }

    /**
     * @return non-empty-list<array-key>
     */
    private function loadForAlternation(AlternationNode $choice): array
    {
        $choices = [];

        foreach ($choice->statements as $stmt) {
            $choices[] = $this->map($this->reduce($stmt));

            /** @var string $relation */
            foreach (\array_diff_assoc($choices, \array_unique($choices)) as $relation) {
                $error = 'The alternation (OR condition) contains excess repeating relation %s';
                throw new GrammarException(\sprintf($error, $relation), $stmt->file, $stmt->offset);
            }
        }

        /** @var non-empty-list<array-key> */
        return $choices;
    }

    private function map(RuleInterface|int|string $rule): int|string
    {
        if ($rule instanceof RuleInterface) {
            return $this->register($rule);
        }

        return $rule;
    }

    private function load(Statement|string|int|array $stmt): string|int|array
    {
        if (\is_array($stmt)) {
            /** @psalm-suppress InvalidArgument */
            return $this->mapAll($this->reduceAll($stmt));
        }

        return $this->map($this->reduce($stmt));
    }

    /**
     * @param list<RuleInterface> $rules
     *
     * @return list<int|string>
     */
    private function mapAll(array $rules): array
    {
        $result = [];

        foreach ($rules as $rule) {
            $result[] = $this->map($rule);
        }

        return $result;
    }

    /**
     * @param list<Statement> $statements
     *
     * @return list<int|string|RuleInterface>
     * @throws NotAccessibleException
     * @throws ParserRuntimeException
     * @throws \RuntimeException
     */
    private function reduceAll(array $statements): array
    {
        $result = [];

        foreach ($statements as $stmt) {
            $result[] = $this->reduce($stmt);
        }

        return $result;
    }

    /**
     * @throws NotAccessibleException
     * @throws \RuntimeException
     */
    private function tokenRelation(TokenNode $token): Lexeme
    {
        if ($this->ids->lexeme($token->name) === null) {
            $error = \sprintf('Token "%s" has not been defined', $token->name);

            throw new GrammarException($error, $token->file, $token->offset);
        }

        return new Lexeme($token->name, $token->keep);
    }

    /**
     * @return non-empty-string|int<0, max>
     * @throws NotAccessibleException
     * @throws \RuntimeException
     */
    private function ruleRelation(RuleNode $rule): int|string
    {
        if ($this->ids->rule($rule->name) === null) {
            $error = \sprintf('Rule "%s" has not been defined', $rule->name);

            throw new GrammarException($error, $rule->file, $rule->offset);
        }

        return $this->name($rule->name);
    }
}
