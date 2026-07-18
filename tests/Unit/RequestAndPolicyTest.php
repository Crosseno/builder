<?php

declare(strict_types=1);

namespace Crosseno\Builder\Tests\Unit;

use Crosseno\Builder\Exception\InvalidBuildRequest;
use Crosseno\Builder\Policy\FailurePolicy;
use Crosseno\Builder\Policy\FallbackStep;
use Crosseno\Builder\Request\BuildRequest;
use Crosseno\Builder\Tests\Support\FixtureFactory;
use Crosseno\Core\Grid\GridDimensions;
use Crosseno\Generator\Strategy\GenerationStrategy;
use Crosseno\Lexicon\Candidate\Theme;
use PHPUnit\Framework\TestCase;

final class RequestAndPolicyTest extends TestCase
{
    public function testCanonicalRequestHashIsStableForSetLikeInputOrdering(): void
    {
        $base = FixtureFactory::request();
        $records = FixtureFactory::records();
        $left = self::copy($base, [new Theme('zoology'), new Theme('arts')], [$records[1]->key, $records[0]->key]);
        $right = self::copy($base, [new Theme('arts'), new Theme('zoology')], [$records[0]->key, $records[1]->key]);

        self::assertSame($left->canonicalJson(), $right->canonicalJson());
        self::assertSame($left->requestHash(), $right->requestHash());
    }

    public function testFailurePolicyRequiresExplicitTerminalAction(): void
    {
        $this->expectException(InvalidBuildRequest::class);

        new FailurePolicy([FallbackStep::retry(), FallbackStep::strategy(GenerationStrategy::Fast)]);
    }

    /** @param list<Theme> $themes @param list<\Crosseno\Lexicon\Identity\StableAnswerKey> $exclusions */
    private static function copy(BuildRequest $request, array $themes, array $exclusions): BuildRequest
    {
        return new BuildRequest(
            $request->answerLanguage,
            $request->clueLanguage,
            $request->dimensions,
            $request->strategy,
            $request->difficulty,
            $request->clueMode,
            $request->proficiency,
            $request->seed,
            $themes,
            $exclusions,
            $request->recentUse,
            $request->qualityThreshold,
            $request->failurePolicy,
            $request->workBudget,
            $request->minimumEntryLength,
            $request->maximumEntryLength,
            $request->validationProfile,
            $request->resourceLimits,
        );
    }
}
