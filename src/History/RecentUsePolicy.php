<?php

declare(strict_types=1);

namespace Crosseno\Builder\History;

use Crosseno\Builder\Exception\InvalidBuildRequest;

final readonly class RecentUsePolicy implements \JsonSerializable
{
    public function __construct(
        public int $maximumBuilds,
        public MissingHistoryPolicy $missingHistory,
    ) {
        if ($maximumBuilds < 0 || $maximumBuilds > 10_000) {
            throw new InvalidBuildRequest('Recent-use build count must be from 0 through 10,000.');
        }
    }

    /** @return array{maximum_builds: int, missing_history: string} */
    public function jsonSerialize(): array
    {
        return ['maximum_builds' => $this->maximumBuilds, 'missing_history' => $this->missingHistory->value];
    }
}
