<?php

declare(strict_types=1);

namespace Crosseno\Builder\Generation;

use Crosseno\Clues\Contract\ClueProviderInterface;
use Crosseno\Lexicon\Candidate\CandidateConstraints;
use Crosseno\Lexicon\Contract\SolverIndexInterface;

final readonly class Eligibility
{
    public function __construct(
        public CandidateConstraints $constraints,
        public SolverIndexInterface $solverIndex,
        public ClueProviderInterface $clueProvider,
        public int $eligibleLearningAnswers,
    ) {}
}
