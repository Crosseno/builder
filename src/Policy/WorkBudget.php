<?php

declare(strict_types=1);

namespace Crosseno\Builder\Policy;

use Crosseno\Builder\Exception\InvalidBuildRequest;

final readonly class WorkBudget implements \JsonSerializable
{
    public function __construct(
        public int $maximumRuns,
        public int $maximumAttempts,
        public int $maximumNodes,
        public int $maximumBacktracks,
        public ?int $maximumDurationMilliseconds = null,
    ) {
        if ($maximumRuns < 1 || $maximumRuns > 100 || $maximumAttempts < 1 || $maximumNodes < 1 || $maximumBacktracks < 0
            || ($maximumDurationMilliseconds !== null && $maximumDurationMilliseconds < 1)) {
            throw new InvalidBuildRequest('Global builder work budget is invalid.');
        }
    }

    /** @return array{maximum_runs: int, maximum_attempts: int, maximum_nodes: int, maximum_backtracks: int, maximum_duration_ms: ?int} */
    public function jsonSerialize(): array
    {
        return [
            'maximum_runs' => $this->maximumRuns,
            'maximum_attempts' => $this->maximumAttempts,
            'maximum_nodes' => $this->maximumNodes,
            'maximum_backtracks' => $this->maximumBacktracks,
            'maximum_duration_ms' => $this->maximumDurationMilliseconds,
        ];
    }
}
