<?php

declare(strict_types=1);

namespace Crosseno\Builder\Policy;

use Crosseno\Builder\Exception\InvalidBuildRequest;

final readonly class FailurePolicy implements \JsonSerializable
{
    /** @var list<FallbackStep> */
    private array $steps;

    /** @param list<FallbackStep> $steps */
    public function __construct(array $steps)
    {
        if (!array_is_list($steps) || $steps === [] || \count($steps) > 32) {
            throw new InvalidBuildRequest('Failure policy must contain from 1 through 32 ordered steps.');
        }
        foreach ($steps as $step) {
            if (!$step instanceof FallbackStep) {
                throw new InvalidBuildRequest('Failure policy contains an invalid fallback step.');
            }
        }
        $terminal = $steps[array_key_last($steps)]->action;
        if ($terminal !== FallbackAction::Postpone && $terminal !== FallbackAction::Fail) {
            throw new InvalidBuildRequest('Failure policy must end with postpone or fail.');
        }
        $this->steps = $steps;
    }

    public static function fail(): self
    {
        return new self([FallbackStep::fail()]);
    }

    /** @return list<FallbackStep> */
    public function steps(): array
    {
        return $this->steps;
    }

    /** @return list<FallbackStep> */
    public function jsonSerialize(): array
    {
        return $this->steps;
    }
}
