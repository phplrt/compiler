<?php

/**
 * This file is part of phplrt package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phplrt\Compiler\Generator;

use PhpParser\Node\Name;
use Phplrt\Compiler\Analyzer;
use Phplrt\Compiler\Extractor;
use Phplrt\Contracts\Grammar\RuleInterface;
use Phplrt\Source\Exception\NotFoundException;
use Phplrt\Source\Exception\NotReadableException;

/**
 * Class Generator
 */
abstract class Generator implements GeneratorInterface
{
    /**
     * @var Extractor
     */
    public $importer;

    /**
     * @var string|null
     */
    protected $namespace;

    /**
     * @var string
     */
    protected $class;

    /**
     * @var string
     */
    protected $fqn;

    /**
     * @var Analyzer
     */
    protected $analyzer;

    /**
     * @var array|string[]
     */
    protected $constants = [];

    /**
     * @var array|string[]
     */
    protected $properties = [];

    /**
     * @var array|string[]
     */
    protected $methods = [];

    /**
     * @var array
     */
    private $preloaded = [];

    /**
     * Generator constructor.
     *
     * @param Analyzer $analyzer
     * @param string $fqn
     */
    public function __construct(Analyzer $analyzer, string $fqn)
    {
        $this->analyzer = $analyzer;
        $this->bootFqn($fqn);

        $this->importer = new Extractor();
    }

    /**
     * @return iterable|string[]
     * @throws NotFoundException
     * @throws NotReadableException
     * @throws \ReflectionException
     */
    public function getImports(): iterable
    {
        foreach ($this->preloaded as $item) {
            $this->importer->loadClass($item);
            $this->importer->replace($item, $this->classNameHash($item));
        }

        foreach ($this->preloaded as $fqn => $item) {
            yield $fqn => $this->importer->get($item);
        }
    }

    /**
     * @param string $fqn
     * @return bool
     */
    public function isImported(string $fqn): bool
    {
        return isset($this->preloaded[$fqn]);
    }

    /**
     * @param string $fqn
     * @return void
     */
    private function bootFqn(string $fqn): void
    {
        $this->fqn = '\\' . \trim($fqn, '\\');

        $chunks = \explode('\\', \trim($this->fqn, '\\'));
        $this->class = \array_pop($chunks);
        $this->namespace = \implode('\\', $chunks) ?: null;
    }

    /**
     * @param string ...$classes
     * @return $this
     */
    public function preload(string ...$classes): self
    {
        foreach ($classes as $class) {
            $this->preloaded[(new Name($class))->toString()] = $class;
        }

        return $this;
    }

    /**
     * @param string $class
     * @return string
     */
    public function classNameHash(string $class): string
    {
        $name = new Name($class);

        return '⠀' . \end($name->parts) . '·' . \hash('sha1', $this->fqn);
    }

    /**
     * @param string $fqn
     * @return string
     */
    public function hashIfImported(string $fqn): string
    {
        return $this->isImported($fqn)
            ? $this->classNameHash($fqn)
            : $this->fqn($fqn);
    }

    /**
     * @return array|RuleInterface[]
     */
    public function getRules(): array
    {
        $rules = $this->analyzer->rules;

        \uksort($rules, static function ($a, $b): int {
            if (\is_string($a) && \is_string($b)) {
                return $a <=> $b;
            }

            if (\is_string($a)) {
                return 1;
            }

            if (\is_string($b)) {
                return -1;
            }

            return $a <=> $b;
        });

        return $rules;
    }

    /**
     * @return array|string
     */
    abstract public function getTokens(): array;

    /**
     * @param string $class
     * @return string
     */
    protected function fqn(string $class): string
    {
        return '\\' . \ltrim($class, '\\');
    }

    /**
     * @param array|string[] $lines
     * @return string
     */
    protected function comment(array $lines): string
    {
        $result = [];

        foreach ($lines as $name => $value) {
            $value = \is_array($value) ? \implode('|', $value) : $value;

            $result[] = \is_string($name) ? \sprintf('@%s %s', $name, $value) : $value;
        }

        return $this->arrayToString($result);
    }

    /**
     * @param array $lines
     * @return string
     */
    protected function arrayToString(array $lines): string
    {
        return \trim(\implode("\n", $lines));
    }

    /**
     * @param string $name
     * @return string
     */
    protected function constantName(string $name): string
    {
        return \strtoupper($name);
    }
}
