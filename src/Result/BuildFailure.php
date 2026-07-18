<?php

declare(strict_types=1);

namespace Crosseno\Builder\Result;

final readonly class BuildFailure implements \JsonSerializable
{
    /** @param array<string, bool|int|string|null> $context */
    public function __construct(
        public string $code,
        public string $message,
        public array $context = [],
    ) {}

    /** @return array{code: string, message: string, context: array<string, bool|int|string|null>} */
    public function jsonSerialize(): array
    {
        return ['code' => $this->code, 'message' => $this->message, 'context' => $this->context];
    }
}
