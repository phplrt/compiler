<?php

declare(strict_types=1);

namespace Phplrt\Compiler\Grammar;

use Phplrt\Contracts\Source\ReadableInterface;
use Phplrt\Lexer\PositionalLexerInterface;
use Phplrt\Lexer\Token\EndOfInput;
use Phplrt\Lexer\Token\Token;
use Phplrt\Source\File;

class PhpLexer implements PositionalLexerInterface
{
    public function __construct(
        private readonly bool $inline = true,
    ) {}

    public function lex(mixed $source, int $offset = 0): iterable
    {
        $tokens = \token_get_all($this->read(File::new($source), $offset));

        foreach ($tokens as $i => $token) {
            if ($this->inline && $i === 0) {
                continue;
            }

            if (\is_array($token)) {
                yield new Token($this->getName($token[0]), $token[1], $offset);

                $offset += \strlen($token[1]);

                continue;
            }

            yield new Token($this->getName($token), $token, $offset);

            $offset += \strlen($token);
        }

        yield new EndOfInput($offset);
    }

    private function read(ReadableInterface $readable, int $offset): string
    {
        $source = $readable->getContents();

        $prefix = $this->inline ? '<?php ' : '';

        return $prefix . ($offset === 0 ? $source : \substr($source, $offset));
    }

    private function getName(int|string $id): string
    {
        if (\is_string($id)) {
            return $id;
        }

        return \token_name($id);
    }
}
