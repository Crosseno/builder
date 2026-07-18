<?php

declare(strict_types=1);

namespace Crosseno\Builder\Result;

final readonly class VersionSnapshot implements \JsonSerializable
{
    public function __construct(
        public string $builder,
        public string $generator,
        public string $answerPackId,
        public string $answerPackVersion,
        public string $cluePackId,
        public string $cluePackVersion,
        public ?string $learningPackId,
        public ?string $learningPackVersion,
    ) {}

    /** @return array<string, ?string> */
    public function jsonSerialize(): array
    {
        return [
            'builder' => $this->builder,
            'generator' => $this->generator,
            'answerPackId' => $this->answerPackId,
            'answerPackVersion' => $this->answerPackVersion,
            'cluePackId' => $this->cluePackId,
            'cluePackVersion' => $this->cluePackVersion,
            'learningPackId' => $this->learningPackId,
            'learningPackVersion' => $this->learningPackVersion,
        ];
    }
}
