<?php

declare(strict_types=1);

namespace Crosseno\Builder\Generation;

use Crosseno\Builder\Exception\BuilderException;
use Crosseno\Lexicon\Candidate\CandidateQuery;
use Crosseno\Lexicon\Candidate\CandidateSet;
use Crosseno\Lexicon\Contract\SolverIndexInterface;
use Crosseno\Lexicon\Identity\StableAnswerKey;

final readonly class EligibilitySolverIndex implements SolverIndexInterface
{
    /** @var array<string, true> */
    private array $allowed;

    /** @param list<StableAnswerKey> $allowed */
    public function __construct(private SolverIndexInterface $inner, array $allowed)
    {
        $map = [];
        foreach ($allowed as $key) {
            $map[$key->coreKey->value] = true;
        }
        $this->allowed = $map;
    }

    public function candidates(CandidateQuery $query): CandidateSet
    {
        $expanded = new CandidateQuery($query->pattern, $query->constraints, CandidateQuery::MAXIMUM_RESULTS, $query->ordering);
        $candidates = $this->inner->candidates($expanded);
        $eligible = array_values(array_filter(
            $candidates->records(),
            fn($record): bool => isset($this->allowed[$record->key->coreKey->value]),
        ));
        if ($candidates->truncated() && \count($eligible) < $query->maximumResults) {
            throw new BuilderException('Solver index cannot safely apply the eligibility mask because its bounded result was truncated.');
        }

        return new CandidateSet(\array_slice($eligible, 0, $query->maximumResults), \count($eligible));
    }
}
