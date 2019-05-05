<?php
/**
 * This file is part of Phplrt package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Phplrt\Compiler\Grammar\Delegate;

use Phplrt\Ast\Rule;

/**
 * Class TokenDelegate
 */
class TokenDelegate extends Rule
{
    /**
     * @return bool
     */
    public function isKept(): bool
    {
        return $this->getChild(0)->getName() === 'T_TOKEN';
    }

    /**
     * @return string
     */
    public function getTokenName(): string
    {
        return $this->getChild(0)->getValue(1);
    }

    /**
     * @return string
     */
    public function getTokenPattern(): string
    {
        return $this->getChild(0)->getValue(2);
    }
}
