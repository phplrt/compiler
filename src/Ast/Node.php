<?php

declare(strict_types=1);

namespace Phplrt\Compiler\Ast;

use Phplrt\Contracts\Ast\NodeInterface;
use Phplrt\Contracts\Source\ReadableInterface;

/**
 * @internal this is an internal class, please do not use it in your application code
 * @psalm-internal Phplrt\Compiler
 */
abstract class Node implements NodeInterface
{
    public ReadableInterface $file;

    /**
     * @var int<0, max>
     */
    public int $offset = 0;

    public function getIterator(): \Traversable
    {
        return new \EmptyIterator();
    }
}
