<?php

declare(strict_types=1);

namespace Phplrt\Compiler;

use Phplrt\Compiler\Ast\Expr\IncludeExpr;
use Phplrt\Compiler\Ast\Node;
use Phplrt\Compiler\Exception\GrammarException;
use Phplrt\Contracts\Ast\NodeInterface;
use Phplrt\Source\Exception\NotAccessibleException;
use Phplrt\Visitor\Visitor;

class IncludesExecutor extends Visitor
{
    /**
     * @var string
     */
    private const ERROR_NOT_FOUND = '%s: failed to open stream: No such file or directory';

    /**
     * @var string[]
     */
    private const FILE_EXTENSIONS = ['', '.pp2', '.pp'];

    /**
     * @param \Closure(non-empty-string):iterable<Node> $loader
     */
    public function __construct(
        private readonly \Closure $loader,
    ) {}

    /**
     * @throws NotAccessibleException
     * @throws \RuntimeException
     */
    public function leave(NodeInterface $node): mixed
    {
        if ($node instanceof IncludeExpr) {
            return $this->lookup($node);
        }

        return $node;
    }

    /**
     * @throws NotAccessibleException
     * @throws \RuntimeException
     */
    private function lookup(IncludeExpr $expr): array
    {
        $pathname = $expr->getTargetPathname();

        foreach (self::FILE_EXTENSIONS as $ext) {
            if (\is_file($pathname . $ext)) {
                return $this->execute($pathname . $ext);
            }
        }

        $message = \sprintf(self::ERROR_NOT_FOUND, $expr->render());

        throw new GrammarException($message, $expr->file, $expr->offset);
    }

    /**
     * @psalm-taint-sink file $pathname
     * @param non-empty-string $pathname
     *
     * @return iterable<Node>
     */
    private function execute(string $pathname): iterable
    {
        return ($this->loader)($pathname);
    }
}
