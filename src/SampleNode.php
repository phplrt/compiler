<?php

declare(strict_types=1);

namespace Phplrt\Compiler;

use Phplrt\Contracts\Ast\NodeInterface;
use Phplrt\Contracts\Lexer\TokenInterface;

class SampleNode implements NodeInterface, \Stringable
{
    /**
     * @param int<0, max> $offset
     * @param non-empty-string $state
     * @param array<array-key, SampleNode> $children
     */
    public function __construct(private int $offset, private string $state, public array $children) {}

    /**
     * @return \Traversable<non-empty-string, array<array-key, SampleNode>>
     */
    public function getIterator(): \Traversable
    {
        yield 'children' => $this->children;
    }

    public function __toString(): string
    {
        return \implode("\n", $this->render(0));
    }

    /**
     * @param int<0, max> $depth
     * @return array<string>
     */
    public function render(int $depth): array
    {
        $prefix = \str_repeat('    ', $depth);

        $result = [
            $prefix . '<' . $this->state . ' offset="' . $this->offset . '">',
        ];

        foreach ($this->children as $child) {
            switch (true) {
                case $child instanceof self:
                    /** @psalm-suppress RedundantFunctionCall: PHP 7.4 unpacking expect only integer keys */
                    $result = [
                        ...\array_values($result),
                        ...\array_values($child->render($depth + 1))
                    ];
                    break;

                case $child instanceof TokenInterface:
                    $result[] = $prefix . '    <' . $child->getName() . ' offset="' . $child->getOffset() . '">' .
                        $child->getValue() . '</' . $child->getName() . '>';
                    break;

                default:
                    $result[] = $prefix . '    <' . $child . ' />';
            }
        }

        $result[] = $prefix . '</' . $this->state . '>';

        return $result;
    }

    public function getState(): string
    {
        return $this->state;
    }
}
