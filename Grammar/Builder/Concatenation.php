<?php
/**
 * This file is part of phplrt package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Phplrt\Compiler\Grammar\Builder;

use Phplrt\Parser\Rule\Concatenation as ConcatenationRule;
use Phplrt\Parser\Rule\Rule;

/**
 * Class Concatenation
 */
class Concatenation extends AbstractBuilder
{
    /**
     * @return Rule|ConcatenationRule
     */
    public function build(): Rule
    {
        $rule = new ConcatenationRule($this->name, $this->children, $this->nodeId);
        $rule->setDefaultId($this->defaultId);

        return $rule;
    }
}
