<?php

declare(strict_types=1);

namespace Crosseno\Builder\Generation;

use Crosseno\Builder\Exception\BuilderException;
use Crosseno\Builder\History\UsageHistorySnapshot;
use Crosseno\Builder\Pack\PackDescriptor;
use Crosseno\Builder\Request\BuildRequest;
use Crosseno\Builder\Request\ClueMode;
use Crosseno\Learning\Model\CoverageQuery;
use Crosseno\Learning\Model\LanguagePair;
use Crosseno\Lexicon\Candidate\CandidateConstraints;
use Crosseno\Lexicon\Candidate\EmptyFilterSemantics;
use Crosseno\Lexicon\Candidate\FlagConstraint;
use Crosseno\Lexicon\Candidate\LanguageFilter;
use Crosseno\Lexicon\Candidate\UnknownValuePolicy;
use Crosseno\Lexicon\Language\LanguageMatchingPolicy;

final readonly class EligibilityBuilder
{
    public function build(BuildRequest $request, PackDescriptor $pack, UsageHistorySnapshot $history): Eligibility
    {
        $exclusions = [];
        foreach ([...$request->excludedAnswerKeys(), ...$history->answerKeys()] as $key) {
            $exclusions[$key->coreKey->value] = $key;
        }
        ksort($exclusions, SORT_STRING);
        $clueCoverage = [new LanguageFilter($request->clueLanguage, LanguageMatchingPolicy::Exact)];
        $constraints = new CandidateConstraints(
            $request->difficulty->answerRange(),
            UnknownValuePolicy::Exclude,
            [],
            EmptyFilterSemantics::MatchAll,
            FlagConstraint::Any,
            FlagConstraint::Any,
            [],
            EmptyFilterSemantics::MatchAll,
            $clueCoverage,
            EmptyFilterSemantics::MatchNone,
            array_values($exclusions),
            $request->themes(),
            $request->themes() === [] ? EmptyFilterSemantics::MatchAll : EmptyFilterSemantics::MatchNone,
        );

        $excludedClues = array_map(static fn($id): string => $id->value, $history->clueIds());
        $solver = $pack->answerPack->lexicon();
        $allowedClues = null;
        $eligibleAnswers = $pack->answerPack->manifest()->recordCount;
        if ($request->clueMode === ClueMode::Learning || $request->answerLanguage->value !== $request->clueLanguage->value) {
            if ($pack->learningPack === null || $request->proficiency === null) {
                throw new BuilderException('Resolved bilingual build lacks a learning pack or proficiency.');
            }
            $coverageQuery = new CoverageQuery(
                new LanguagePair($request->answerLanguage, $request->clueLanguage),
                $request->proficiency,
                recentClueIds: $history->clueIds(),
                excludedAnswerKeys: array_values($exclusions),
                maximumResults: 1_000_000,
            );
            $mask = $pack->learningPack->coverageIndex()->query($coverageQuery);
            $mask->assertCompatible($pack->answerPack->manifest());
            $allowedAnswers = [];
            foreach ($mask->ordinals() as $ordinal) {
                $allowedAnswers[] = $pack->answersByOrdinal()[$ordinal];
            }
            $solver = new EligibilitySolverIndex($solver, $allowedAnswers);
            $eligibleAnswers = \count($allowedAnswers);
            $allowedClues = array_map(
                static fn($clue): string => $clue->clueId->value,
                $pack->learningPack->catalog()->eligible($coverageQuery),
            );
        }

        return new Eligibility(
            $constraints,
            $solver,
            new FilteringClueProvider($pack->clueProvider, $allowedClues, $excludedClues),
            $eligibleAnswers,
        );
    }
}
