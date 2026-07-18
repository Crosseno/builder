<?php

declare(strict_types=1);

namespace Crosseno\Builder\Quality;

use Crosseno\Builder\Exception\InvalidBuildRequest;

final readonly class PublicationQuality implements \JsonSerializable
{
    public function __construct(
        public int $structural,
        public int $lexical,
        public int $clues,
        public int $completeness,
        public int $final,
    ) {
        foreach (get_object_vars($this) as $score) {
            if ($score < 0 || $score > 1_000_000) {
                throw new InvalidBuildRequest('Publication quality scores must be integer millionths.');
            }
        }
    }

    /** @return array{structural: int, lexical: int, clues: int, completeness: int, final: int} */
    public function jsonSerialize(): array
    {
        return [
            'structural' => $this->structural,
            'lexical' => $this->lexical,
            'clues' => $this->clues,
            'completeness' => $this->completeness,
            'final' => $this->final,
        ];
    }
}
