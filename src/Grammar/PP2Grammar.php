<?php

declare(strict_types=1);

namespace Phplrt\Compiler\Grammar;

use Phplrt\Compiler\Node\Definition\PragmaDefinitionNode;
use Phplrt\Compiler\Node\Definition\RuleDefinitionNode;
use Phplrt\Compiler\Node\Definition\TokenDefinitionNode;
use Phplrt\Compiler\Node\Expression\IncludeNode;
use Phplrt\Compiler\Node\Node;
use Phplrt\Compiler\Node\Statement\AlternationNode;
use Phplrt\Compiler\Node\Statement\ClassLanguageInjection;
use Phplrt\Compiler\Node\Statement\ConcatenationNode;
use Phplrt\Compiler\Node\Statement\LanguageInjection;
use Phplrt\Compiler\Node\Statement\PatternNode;
use Phplrt\Compiler\Node\Statement\RepetitionQuantifierNode;
use Phplrt\Compiler\Node\Statement\RepetitionNode;
use Phplrt\Compiler\Node\Statement\RuleNode;
use Phplrt\Compiler\Node\Statement\TokenNode;
use Phplrt\Contracts\Ast\NodeInterface;
use Phplrt\Contracts\Lexer\TokenInterface;
use Phplrt\Lexer\Multistate;
use Phplrt\Lexer\Token\Composite;
use Phplrt\Parser\BuilderInterface;
use Phplrt\Parser\Context;
use Phplrt\Parser\Grammar\Alternation;
use Phplrt\Parser\Grammar\Concatenation;
use Phplrt\Parser\Grammar\Lexeme;
use Phplrt\Parser\Grammar\Optional;
use Phplrt\Parser\Grammar\Repetition;
use Phplrt\Parser\Grammar\RuleInterface;
use Phplrt\Parser\Parser;
use Phplrt\Parser\ParserConfigsInterface;

class PP2Grammar implements GrammarInterface, BuilderInterface
{
    private Parser $runtime;

    /**
     * @var array<array-key, \Closure>
     */
    private array $reducers = [];

    public function __construct()
    {
        $lexer = new Multistate([
            new PP2Lexer(),
            new PP2PHPLexer(new PhpLexer()),
        ], [
            ['T_PHP_OPEN' => 1],
            ['T_PHP_CODE' => 0],
        ]);

        $this->reducers = $this->reducers();

        $this->runtime = new Parser($lexer, $this->grammar(), [
            ParserConfigsInterface::CONFIG_INITIAL_RULE => 0,
            ParserConfigsInterface::CONFIG_AST_BUILDER => $this,
            ParserConfigsInterface::CONFIG_STEP_REDUCER => $this->next(...),
        ]);
    }

    /**
     * @return array<array-key, \Closure>
     * @psalm-suppress all
     */
    private function reducers(): array
    {
        return [
            20 => static function (array $delegates): NodeInterface {
                if ($delegates === []) {
                    return new LanguageInjection(null);
                }

                $delegate = \reset($delegates);

                if ($delegate->getName() === 'T_PHP_CODE') {
                    return new LanguageInjection(\trim((string) $delegate->getValue(), "\r\n"));
                }

                return new ClassLanguageInjection($delegate->getValue());
            },
            14 => static function (Composite $include): NodeInterface {
                return new IncludeNode($include[0]->getValue());
            },
            13 => static function (Composite $pragma): NodeInterface {
                return new PragmaDefinitionNode($pragma[0]->getValue(), $pragma[1]->getValue());
            },
            15 => static function (Composite $token): NodeInterface {
                /** @var TokenInterface[] $token */
                [$state, $name, $pattern, $next] = $token;

                $result = new TokenDefinitionNode($name->getValue(), $pattern->getValue());
                $value = $state->getValue();

                if ($value !== '') {
                    $result->state = $state->getValue();
                }

                if ($next) {
                    $result->next = $next->getValue();
                }

                return $result;
            },
            16 => static function (Composite $skip): NodeInterface {
                return new TokenDefinitionNode($skip[0]->getValue(), $skip[1]->getValue(), false);
            },
            17 => static function (array $sequence): NodeInterface {
                [$name, $keep, $delegate, $stmt] = $sequence;

                return new RuleDefinitionNode($name, $delegate, $stmt, $keep);
            },
            18 => static function (array $name): array {
                return [$name[0]->getValue(), true];
            },
            19 => static function (array $name): array {
                return [$name[0]->getValue(), false];
            },
            27 => static function (TokenInterface $name): array {
                return [$name];
            },
            34 => static function (Composite $invocation): NodeInterface {
                return new RuleNode($invocation[0]->getValue());
            },
            32 => static function (Composite $token): NodeInterface {
                return new TokenNode($token[0]->getValue(), true);
            },
            31 => static function (Composite $skip): NodeInterface {
                return new TokenNode($skip[0]->getValue(), false);
            },
            33 => static function (Composite $invocation): NodeInterface {
                return new PatternNode($invocation[0]->getValue());
            },
            21 => static function (array $statements): NodeInterface {
                return new AlternationNode($statements);
            },
            22 => static function (array $statements): NodeInterface {
                return new ConcatenationNode($statements);
            },
            24 => static function (array $payload): NodeInterface {
                [$stmt, $q] = $payload;

                return new RepetitionNode($stmt, $q);
            },
            23 => static function (array $group): NodeInterface {
                return \reset($group);
            },
            37 => static function (): NodeInterface {
                return new RepetitionQuantifierNode(0, 1);
            },
            38 => static function (): NodeInterface {
                return new RepetitionQuantifierNode(1, \INF);
            },
            39 => static function (): NodeInterface {
                return new RepetitionQuantifierNode(0, \INF);
            },
            40 => static function (Composite $value): NodeInterface {
                [$from, $to] = [$value[0]->getValue(), $value[1]->getValue()];

                return new RepetitionQuantifierNode((int) $from, (int) $to);
            },
            42 => static function (Composite $value): NodeInterface {
                return new RepetitionQuantifierNode((int) $value[0]->getValue(), \INF);
            },
            41 => static function (Composite $value): NodeInterface {
                return new RepetitionQuantifierNode(0, (int) $value[0]->getValue());
            },
            43 => static function (Composite $value): NodeInterface {
                $count = (int) $value[0]->getValue();

                return new RepetitionQuantifierNode($count, $count);
            },
        ];
    }

    /**
     * @return array<array-key, RuleInterface>
     */
    private function grammar(): array
    {
        return [
            0  => new Repetition(11, 0),
            1  => new Concatenation([30, 2]),
            2  => new Alternation([22, 24, 5]),
            3  => new Alternation([24, 5]),
            4  => new Repetition(1, 1),
            5  => new Alternation([23, 31, 32, 33, 34]),
            6  => new Alternation([37, 38, 39, 40, 41, 42, 43]),
            7  => new Alternation([21, 22, 24, 5]),
            8  => new Alternation([21, 22, 24, 5]),
            9  => new Optional(29),
            10 => new Alternation([18, 19]),
            11 => new Alternation([15, 16, 13, 14, 17]),
            12 => new Concatenation([25, 27]),
            13 => new Lexeme('T_PRAGMA'),
            14 => new Lexeme('T_INCLUDE'),
            15 => new Lexeme('T_TOKEN_DEF'),
            16 => new Lexeme('T_SKIP_DEF'),
            17 => new Concatenation([10, 20, 28, 8, 9]),
            18 => new Concatenation([26, 27]),
            19 => new Concatenation([27]),
            20 => new Optional(48),
            21 => new Concatenation([2, 4]),
            22 => new Repetition(3, 2),
            23 => new Concatenation([35, 7, 36]),
            24 => new Concatenation([5, 6]),
            25 => new Lexeme('T_ARROW_RIGHT', false),
            26 => new Lexeme('T_KEPT_NAME', false),
            27 => new Lexeme('T_NAME'),
            28 => new Lexeme('T_EQ', false),
            29 => new Lexeme('T_END_OF_RULE', false),
            30 => new Lexeme('T_OR', false),
            31 => new Lexeme('T_TOKEN_SKIPPED'),
            32 => new Lexeme('T_TOKEN_KEPT'),
            33 => new Lexeme('T_TOKEN_STRING'),
            34 => new Lexeme('T_INVOKE'),
            35 => new Lexeme('T_GROUP_OPEN', false),
            36 => new Lexeme('T_GROUP_CLOSE', false),
            37 => new Lexeme('T_REPEAT_ZERO_OR_ONE'),
            38 => new Lexeme('T_REPEAT_ONE_OR_MORE'),
            39 => new Lexeme('T_REPEAT_ZERO_OR_MORE'),
            40 => new Lexeme('T_REPEAT_N_TO_M'),
            41 => new Lexeme('T_REPEAT_ZERO_TO_M'),
            42 => new Lexeme('T_REPEAT_N_OR_MORE'),
            43 => new Lexeme('T_REPEAT_EXACTLY_N'),
            44 => new Concatenation([45, 47, 46]),
            45 => new Lexeme('T_PHP_OPEN', false),
            46 => new Lexeme('T_PHP_CLOSE', false),
            47 => new Lexeme('T_PHP_CODE'),
            48 => new Alternation([12, 44]),
        ];
    }

    protected function next(Context $context, \Closure $next): mixed
    {
        $offset = $context->getToken()->getOffset();

        /** @var mixed $result */
        $result = $next($context);

        if ($result instanceof Node) {
            $result->offset = $offset;
            $result->file = $context->getSource();
        }

        return $result;
    }

    public function parse(mixed $source, array $options = []): iterable
    {
        return $this->runtime->parse($source, $options);
    }

    public function build(Context $context, mixed $result): mixed
    {
        if (isset($this->reducers[$context->getState()])) {
            return $this->reducers[$context->getState()]($result);
        }

        return null;
    }
}
