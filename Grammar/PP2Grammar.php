<?php
/**
 * This file is part of phplrt package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Phplrt\Compiler\Grammar;

use Phplrt\Parser\Parser;
use Phplrt\Position\Position;
use Phplrt\Parser\Rule\Lexeme;
use Phplrt\Parser\Rule\Optional;
use Phplrt\Lexer\Token\Composite;
use Phplrt\Parser\Rule\Repetition;
use Phplrt\Parser\Rule\Alternation;
use Phplrt\Compiler\Ast\Def\RuleDef;
use Phplrt\Parser\Rule\RuleInterface;
use Phplrt\Parser\Rule\Concatenation;
use Phplrt\Parser\Builder\Expandable;
use Phplrt\Compiler\Ast\Def\TokenDef;
use Phplrt\Compiler\Ast\Stmt\RuleStmt;
use Phplrt\Compiler\Ast\Def\PragmaDef;
use Phplrt\Compiler\Ast\Stmt\TokenStmt;
use Phplrt\Compiler\Ast\Stmt\Quantifier;
use Phplrt\Compiler\Ast\Expr\IncludeExpr;
use Phplrt\Parser\Buffer\BufferInterface;
use Phplrt\Compiler\Ast\Stmt\PatternStmt;
use Phplrt\Contracts\Lexer\TokenInterface;
use Phplrt\Compiler\Ast\Stmt\DelegateStmt;
use Phplrt\Parser\Builder\BuilderInterface;
use Phplrt\Compiler\Ast\Stmt\RepetitionStmt;
use Phplrt\Compiler\Ast\Stmt\AlternationStmt;
use Phplrt\Compiler\Ast\Stmt\ConcatenationStmt;
use Phplrt\Compiler\Ast\Stmt\ClassDelegateStmt;

/**
 * Class GrammarParser
 */
class PP2Grammar extends Parser implements GrammarInterface
{
    /**
     * Parser root rule name.
     *
     * @var string
     */
    protected const PARSER_ROOT_RULE = 'Grammar';

    /**
     * Parser constructor.
     */
    public function __construct()
    {
        parent::__construct(new PP2Lexer(), $this->createGrammar());
    }

    /**
     * @return array|RuleInterface[]
     */
    protected function createGrammar(): array
    {
        return [
            'Grammar'                => new Repetition(11, 0, INF),
            11                       => new Alternation([
                'TokenDefinition',
                'SkippedTokenDefinition',
                'PragmaDefinition',
                'Inclusion',
                'RuleDefinition',
            ]),
            'PragmaDefinition'       => new Lexeme('T_PRAGMA'),
            'Inclusion'              => new Lexeme('T_INCLUDE'),
            'TokenDefinition'        => new Lexeme('T_TOKEN'),
            'SkippedTokenDefinition' => new Lexeme('T_SKIP'),
            'RuleDefinition'         => new Concatenation([10, 'RuleDelegate', 'T_EQ', 8, 9]),
            10                       => new Alternation([
                'RuleKeep',
                'RuleSkip',
            ]),
            'RuleKeep'               => new Concatenation(['T_KEPT_NAME', 'T_NAME']),
            'RuleSkip'               => new Concatenation(['T_NAME']),
            'RuleDelegate'           => new Optional(12),
            12                       => new Concatenation(['T_ARROW_RIGHT', 'T_NAME']),
            'T_ARROW_RIGHT'          => new Lexeme('T_ARROW_RIGHT', false),
            'T_KEPT_NAME'            => new Lexeme('T_KEPT_NAME', false),
            'T_NAME'                 => new Lexeme('T_NAME'),
            'T_EQ'                   => new Lexeme('T_EQ', false),
            8                        => new Alternation(['Choice', 'Sequence', 'Repeat', 5]),
            9                        => new Optional('T_END_OF_RULE'),
            'T_END_OF_RULE'          => new Lexeme('T_END_OF_RULE', false),
            'Choice'                 => new Concatenation([2, 4]),
            4                        => new Repetition(1, 1),
            1                        => new Concatenation(['T_OR', 2]),
            'T_OR'                   => new Lexeme('T_OR', false),
            2                        => new Alternation(['Sequence', 'Repeat', 5]),
            'Sequence'               => new Repetition(3, 2),
            3                        => new Alternation(['Repeat', 5]),
            5                        => new Alternation([
                'Group',
                'T_TOKEN_SKIPPED',
                'T_TOKEN_KEPT',
                'T_TOKEN_STRING',
                'T_INVOKE',
            ]),
            'T_TOKEN_SKIPPED'        => new Lexeme('T_TOKEN_SKIPPED'),
            'T_TOKEN_KEPT'           => new Lexeme('T_TOKEN_KEPT'),
            'T_TOKEN_STRING'         => new Lexeme('T_TOKEN_STRING'),
            'T_INVOKE'               => new Lexeme('T_INVOKE'),
            'Group'                  => new Concatenation(['T_GROUP_OPEN', 7, 'T_GROUP_CLOSE']),
            'T_GROUP_OPEN'           => new Lexeme('T_GROUP_OPEN', false),
            'T_GROUP_CLOSE'          => new Lexeme('T_GROUP_CLOSE', false),
            7                        => new Alternation(['Choice', 'Sequence', 'Repeat', 5]),
            'Repeat'                 => new Concatenation([5, 6]),
            6                        => new Alternation([
                'T_REPEAT_ZERO_OR_ONE',
                'T_REPEAT_ONE_OR_MORE',
                'T_REPEAT_ZERO_OR_MORE',
                'T_REPEAT_N_TO_M',
                'T_REPEAT_ZERO_TO_M',
                'T_REPEAT_N_OR_MORE',
                'T_REPEAT_EXACTLY_N',
            ]),
            'T_REPEAT_ZERO_OR_ONE'   => new Lexeme('T_REPEAT_ZERO_OR_ONE'),
            'T_REPEAT_ONE_OR_MORE'   => new Lexeme('T_REPEAT_ONE_OR_MORE'),
            'T_REPEAT_ZERO_OR_MORE'  => new Lexeme('T_REPEAT_ZERO_OR_MORE'),
            'T_REPEAT_N_TO_M'        => new Lexeme('T_REPEAT_N_TO_M'),
            'T_REPEAT_ZERO_TO_M'     => new Lexeme('T_REPEAT_ZERO_TO_M'),
            'T_REPEAT_N_OR_MORE'     => new Lexeme('T_REPEAT_N_OR_MORE'),
            'T_REPEAT_EXACTLY_N'     => new Lexeme('T_REPEAT_EXACTLY_N'),
        ];
    }

    /**
     * @return BuilderInterface
     */
    public function builder(): BuilderInterface
    {
        return new Expandable($this->reducers());
    }

    /**
     * @return array|\Closure[]
     */
    private function reducers(): array
    {
        return [
            'RuleDelegate'           => static function (array $delegate, int $offset) {
                if (\count($delegate)) {
                    return new ClassDelegateStmt($delegate[0]->getValue(), $offset);
                }

                return new DelegateStmt(null, $offset);
            },
            'Inclusion'              => static function (Composite $include, int $offset) {
                return new IncludeExpr($include[0]->getValue(), $offset);
            },
            'PragmaDefinition'       => static function (Composite $pragma, int $offset) {
                return new PragmaDef($pragma[0]->getValue(), $pragma[1]->getValue(), $offset);
            },
            'TokenDefinition'        => static function (Composite $pragma, int $offset) {
                return new TokenDef($pragma[0]->getValue(), $pragma[1]->getValue(), $offset);
            },
            'SkippedTokenDefinition' => static function (Composite $pragma, int $offset) {
                $token = new TokenDef($pragma[0]->getValue(), $pragma[1]->getValue(), $offset);
                $token->keep = false;

                return $token;
            },
            'RuleDefinition'         => static function (array $sequence, int $offset) {
                [$name, $keep, $delegate, $stmt] = $sequence;

                $result = new RuleDef($name, $delegate, $stmt, $offset);
                $result->keep = $keep;

                return $result;
            },
            'RuleKeep'               => static function (array $name) {
                return [$name[0]->getValue(), true];
            },
            'RuleSkip'               => static function (array $name) {
                return [$name[0]->getValue(), false];
            },
            'T_NAME'                 => static function (TokenInterface $name) {
                return [$name];
            },
            'T_INVOKE'               => static function (Composite $invocation, int $offset) {
                return new RuleStmt($invocation[0]->getValue(), $offset);
            },
            'T_TOKEN_KEPT'           => static function (Composite $invocation, int $offset) {
                return new TokenStmt($invocation[0]->getValue(), true, $offset);
            },
            'T_TOKEN_SKIPPED'        => static function (Composite $invocation, int $offset) {
                return new TokenStmt($invocation[0]->getValue(), false, $offset);
            },
            'T_TOKEN_STRING'         => static function (Composite $invocation, int $offset) {
                return new PatternStmt($invocation[0]->getValue(), $offset);
            },
            'Choice'                 => static function (array $statements, int $offset) {
                return new AlternationStmt($statements, $offset);
            },
            'Sequence'               => static function (array $statements, int $offset) {
                return new ConcatenationStmt($statements, $offset);
            },
            'Repeat'                 => static function (array $payload, int $offset) {
                [$stmt, $q] = $payload;

                return new RepetitionStmt($stmt, $q, $offset);
            },
            'Group'                  => static function (array $group) {
                return \reset($group);
            },
            'T_REPEAT_ZERO_OR_ONE'   => static function ($_, int $offset) {
                return new Quantifier(0, 1, $offset);
            },
            'T_REPEAT_ONE_OR_MORE'   => static function ($_, int $offset) {
                return new Quantifier(1, \INF, $offset);
            },
            'T_REPEAT_ZERO_OR_MORE'  => static function ($_, int $offset) {
                return new Quantifier(0, \INF, $offset);
            },
            'T_REPEAT_N_TO_M'        => static function (Composite $value, int $offset) {
                [$from, $to] = [$value[0]->getValue(), $value[1]->getValue()];

                return new Quantifier((int)$from, (int)$to, $offset);
            },
            'T_REPEAT_N_OR_MORE'     => static function (Composite $value, int $offset) {
                return new Quantifier((int)$value[0]->getValue(), \INF, $offset);
            },
            'T_REPEAT_ZERO_TO_M'     => static function (Composite $value, int $offset) {
                return new Quantifier(0, (int)$value[0]->getValue(), $offset);
            },
            'T_REPEAT_EXACTLY_N'     => static function (Composite $value, int $offset) {
                $count = (int)$value[0]->getValue();

                return new Quantifier($count, $count, $offset);
            },
        ];
    }

    /**
     * @param BufferInterface $buffer
     * @param array $rules
     * @return int|mixed|string
     */
    public function getInitialRule(BufferInterface $buffer, array $rules)
    {
        return self::PARSER_ROOT_RULE;
    }
}
