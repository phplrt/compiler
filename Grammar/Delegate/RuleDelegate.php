<?php
/**
 * This file is part of Phplrt package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Phplrt\Compiler\Grammar\Delegate;

use Phplrt\Compiler\Grammar\LookaheadIterator;
use Phplrt\Lexer\Token\EndOfInput;
use Phplrt\Lexer\Token\Token;
use Phplrt\Lexer\TokenInterface;
use Phplrt\Ast\LeafInterface;
use Phplrt\Ast\NodeInterface;
use Phplrt\Ast\Rule;
use Phplrt\Ast\RuleInterface;

/**
 * Class RuleDelegate
 */
class RuleDelegate extends Rule
{
    /**
     * @return iterable|TokenInterface[]|LookaheadIterator
     */
    public function getInnerTokens(): iterable
    {
        return new LookaheadIterator((function () {
            yield from $this->getTokens($this->first('RuleProduction'));
            yield new EndOfInput(0);
        })->call($this));
    }

    /**
     * @param RuleInterface|NodeInterface $rule
     * @return \Traversable
     */
    private function getTokens(RuleInterface $rule): \Traversable
    {
        /** @var LeafInterface $child */
        foreach ($rule->getChildren() as $child) {
            if ($child instanceof RuleInterface) {
                yield from $this->getTokens($child);
            } else {
                yield new Token($child->getName(), $child->getValues(), $child->getOffset());
            }
        }
    }

    /**
     * @return string
     */
    public function getRuleName(): string
    {
        return $this->first('RuleName')->first('T_NAME')->getValue();
    }

    /**
     * @return bool
     */
    public function isKept(): bool
    {
        return (bool)$this->first('ShouldKeep');
    }

    /**
     * @return null|string
     */
    public function getDelegate(): ?string
    {
        $delegate = $this->first('RuleDelegate');

        if ($delegate instanceof RuleInterface) {
            return $delegate->first('T_NAME')->getValue();
        }

        return null;
    }
}
