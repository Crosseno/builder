<?php

declare(strict_types=1);

namespace Crosseno\Builder\Request;

use Crosseno\Builder\Exception\InvalidBuildRequest;

final readonly class IdempotencyKey implements \Stringable
{
    public function __construct(public string $value)
    {
        if ($value === '' || \strlen($value) > 255 || preg_match('/^[\x21-\x7E]+$/D', $value) !== 1) {
            throw new InvalidBuildRequest('Idempotency key must contain 1 through 255 printable ASCII bytes without spaces.');
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
