<?php

declare(strict_types=1);

namespace Crosseno\Builder\Generation;

use Crosseno\Builder\Contract\GeneratorFactoryInterface;
use Crosseno\Generator\Contract\LayoutGeneratorInterface;
use Crosseno\Generator\DeterministicGenerator;
use Crosseno\Generator\Random\RandomizerFactoryInterface;
use Crosseno\Generator\Score\CrosswordScorerInterface;
use Crosseno\Generator\Time\ClockInterface;
use Crosseno\Lexicon\Contract\SolverIndexInterface;
use Crosseno\Lexicon\Manifest\LanguagePackManifest;

final readonly class DefaultGeneratorFactory implements GeneratorFactoryInterface
{
    public function __construct(
        private RandomizerFactoryInterface $randomizers,
        private CrosswordScorerInterface $scorer,
        private ClockInterface $clock,
    ) {}

    public function create(SolverIndexInterface $index, LanguagePackManifest $manifest): LayoutGeneratorInterface
    {
        return new DeterministicGenerator($index, $manifest, $this->randomizers, $this->scorer, $this->clock);
    }
}
