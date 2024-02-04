<?php

declare(strict_types=1);

use Phplrt\Parser\Parser;

return [
    'initial' => 3,
    'tokens' => [
        'd' => '\\d+',
        'p' => '\\+',
        'ws' => '\\s+',
    ],
    'skip' => [
        'ws',
    ],
    'grammar' => [
        new \Phplrt\Parser\Grammar\Concatenation([4, 5]),
        new \Phplrt\Parser\Grammar\Lexeme('d', true),
        new \Phplrt\Parser\Grammar\Repetition(0, 1, INF),
        new \Phplrt\Parser\Grammar\Concatenation([1, 2]),
        new \Phplrt\Parser\Grammar\Lexeme('p', false),
        new \Phplrt\Parser\Grammar\Lexeme('d', true),
    ],
    'reducers' => [
        0 => static function (\Phplrt\Parser\Context $ctx, mixed $children): void {
            dump($children);
        },
        3 => static function (\Phplrt\Parser\Context $ctx, mixed $children): mixed {
            // The "$offset" variable is an auto-generated
            $offset = $ctx->lastProcessedToken->getOffset();

            dump($offset);

            foreach ($children as $child) {
                dump($child);
            }

            return $children;
        },
    ],
];
