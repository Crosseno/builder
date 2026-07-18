<?php

declare(strict_types=1);

namespace Crosseno\Builder\Result;

use Crosseno\Builder\Policy\FallbackAction;

final readonly class FallbackRecord implements \JsonSerializable
{
    public function __construct(
        public int $sequence,
        public FallbackAction $action,
        public string $fromState,
        public ?string $toState,
        public string $reasonCode,
    ) {}

    /** @return array{sequence: int, action: string, from_state: string, to_state: ?string, reason_code: string} */
    public function jsonSerialize(): array
    {
        return [
            'sequence' => $this->sequence,
            'action' => $this->action->value,
            'from_state' => $this->fromState,
            'to_state' => $this->toState,
            'reason_code' => $this->reasonCode,
        ];
    }
}
