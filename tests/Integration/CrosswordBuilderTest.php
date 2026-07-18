<?php

declare(strict_types=1);

namespace Crosseno\Builder\Tests\Integration;

use Crosseno\Builder\Exception\PackResolutionFailed;
use Crosseno\Builder\History\MissingHistoryPolicy;
use Crosseno\Builder\History\UsageHistorySnapshot;
use Crosseno\Builder\Pack\InMemoryPackCatalog;
use Crosseno\Builder\Pack\ManifestCompatibilityValidator;
use Crosseno\Builder\Pack\PackResolver;
use Crosseno\Builder\Pack\RuntimeContractVersions;
use Crosseno\Builder\Policy\FailurePolicy;
use Crosseno\Builder\Policy\FallbackAction;
use Crosseno\Builder\Policy\FallbackStep;
use Crosseno\Builder\Policy\WorkBudget;
use Crosseno\Builder\Request\ClueMode;
use Crosseno\Builder\Request\IdempotencyKey;
use Crosseno\Builder\Result\BuildStatus;
use Crosseno\Builder\Tests\Support\FixtureFactory;
use Crosseno\Builder\Tests\Support\ScenarioGeneratorFactory;
use Crosseno\Builder\Tests\Support\TestClueAssigner;
use Crosseno\Builder\Tests\Support\TestHistory;
use Crosseno\Core\Grid\GridDimensions;
use Crosseno\Generator\Budget\NeverCancelled;
use Crosseno\Generator\Result\GenerationStatus;
use Crosseno\Generator\Strategy\GenerationStrategy;
use Crosseno\Generator\Time\ClockInterface;
use Crosseno\Learning\Model\CefrLevel;
use PHPUnit\Framework\TestCase;

final class CrosswordBuilderTest extends TestCase
{
    public function testOneCallBuildsDeterministicPersistenceReadyMonolingualResult(): void
    {
        $records = FixtureFactory::records();
        $request = FixtureFactory::request();
        $key = new IdempotencyKey('scheduled-2026-07-17');
        $first = FixtureFactory::builder([FixtureFactory::pack($records)], new ScenarioGeneratorFactory(), new TestClueAssigner(), new TestHistory(UsageHistorySnapshot::available()))
            ->build($request, $key, new NeverCancelled());
        $second = FixtureFactory::builder([FixtureFactory::pack($records)], new ScenarioGeneratorFactory(), new TestClueAssigner(), new TestHistory(UsageHistorySnapshot::available()))
            ->build($request, $key, new NeverCancelled());

        self::assertTrue($first->succeeded());
        self::assertSame($first->publicationKey, $second->publicationKey);
        self::assertSame($request->requestHash(), $first->requestHash);
        self::assertCount(2, $first->answerKeys());
        self::assertCount(2, $first->clues?->assignments() ?? []);
        self::assertNotNull($first->generation?->scores);
        self::assertNotNull($first->quality);
        self::assertSame('builder.test.en', $first->versions?->answerPackId);
        self::assertSame('0123456789abcdef', $first->generation?->seed->unsignedHex);
    }

    public function testRetryAndStrategyAndDimensionFallbacksAreRecordedAndBounded(): void
    {
        $policy = new FailurePolicy([
            FallbackStep::retry(),
            FallbackStep::strategy(GenerationStrategy::Fast),
            FallbackStep::dimensions(new GridDimensions(4, 4)),
            FallbackStep::fail(),
        ]);
        $generator = new ScenarioGeneratorFactory([
            GenerationStatus::Unsatisfiable,
            GenerationStatus::Unsatisfiable,
            GenerationStatus::Success,
        ]);
        $result = FixtureFactory::builder(
            [FixtureFactory::pack(FixtureFactory::records())],
            $generator,
            new TestClueAssigner(),
            new TestHistory(UsageHistorySnapshot::available()),
        )->build(FixtureFactory::request(failurePolicy: $policy), new IdempotencyKey('fallback-case'), new NeverCancelled());

        self::assertTrue($result->succeeded());
        self::assertCount(2, $result->fallbacks());
        self::assertSame(FallbackAction::Retry, $result->fallbacks()[0]->action);
        self::assertSame(FallbackAction::Strategy, $result->fallbacks()[1]->action);
        self::assertSame(GenerationStrategy::Fast, $generator->requests[2]->strategyProfile->strategy);
        self::assertNotSame($generator->requests[0]->seed->unsignedHex, $generator->requests[1]->seed->unsignedHex);
        self::assertLessThanOrEqual(5, \count($generator->requests));
    }

    public function testDimensionFallbackIsApplied(): void
    {
        $generator = new ScenarioGeneratorFactory([GenerationStatus::Unsatisfiable, GenerationStatus::Success]);
        $policy = new FailurePolicy([FallbackStep::dimensions(new GridDimensions(4, 4)), FallbackStep::fail()]);
        $result = FixtureFactory::builder([FixtureFactory::pack(FixtureFactory::records())], $generator, new TestClueAssigner(), new TestHistory(UsageHistorySnapshot::available()))
            ->build(FixtureFactory::request(failurePolicy: $policy), new IdempotencyKey('dimension-case'), new NeverCancelled());

        self::assertTrue($result->succeeded());
        self::assertSame(4, $generator->requests[1]->dimensions->rows);
        self::assertSame(FallbackAction::Dimensions, $result->fallbacks()[0]->action);
    }

    public function testPostponePolicyReturnsNoPublishableContent(): void
    {
        $policy = new FailurePolicy([FallbackStep::postpone()]);
        $result = FixtureFactory::builder(
            [FixtureFactory::pack(FixtureFactory::records())],
            new ScenarioGeneratorFactory([GenerationStatus::Unsatisfiable]),
            new TestClueAssigner(),
            new TestHistory(UsageHistorySnapshot::available()),
        )->build(FixtureFactory::request(failurePolicy: $policy), new IdempotencyKey('postpone-case'), new NeverCancelled());

        self::assertSame(BuildStatus::Postponed, $result->status);
        self::assertNull($result->crossword);
        self::assertSame(FallbackAction::Postpone, $result->fallbacks()[0]->action);
    }

    public function testQualityGateAndMissingCluesNeverPublish(): void
    {
        $qualityRejected = FixtureFactory::builder(
            [FixtureFactory::pack(FixtureFactory::records())],
            new ScenarioGeneratorFactory(score: 100_000),
            new TestClueAssigner(score: 0),
            new TestHistory(UsageHistorySnapshot::available()),
        )->build(FixtureFactory::request(qualityThreshold: 900_000), new IdempotencyKey('quality-case'), new NeverCancelled());
        $noClues = FixtureFactory::builder(
            [FixtureFactory::pack(FixtureFactory::records())],
            new ScenarioGeneratorFactory(),
            new TestClueAssigner(fail: true, failureMessage: 'secret adapter path: /srv/private'),
            new TestHistory(UsageHistorySnapshot::available()),
        )->build(FixtureFactory::request(), new IdempotencyKey('clue-case'), new NeverCancelled());

        self::assertSame(BuildStatus::Failed, $qualityRejected->status);
        self::assertSame('quality_threshold_rejected', $qualityRejected->failure?->code);
        self::assertNull($qualityRejected->crossword);
        self::assertSame('no_valid_clues', $noClues->failure?->code);
        self::assertSame('No complete valid clue assignment could be produced.', $noClues->failure?->message);
        self::assertNull($noClues->clues);
    }

    public function testAnswerPackMinimumContractVersionIsEnforced(): void
    {
        $pack = FixtureFactory::pack(FixtureFactory::records());
        $resolver = new PackResolver(
            new InMemoryPackCatalog([$pack]),
            new ManifestCompatibilityValidator(new RuntimeContractVersions(core: '0.0.1')),
        );

        $this->expectException(PackResolutionFailed::class);
        $this->expectExceptionMessage('answer pack requires core contract 0.1.0 or newer');
        $resolver->resolve(FixtureFactory::request());
    }

    public function testLearningPackMinimumContractVersionIsEnforced(): void
    {
        $records = FixtureFactory::records();
        $pack = FixtureFactory::pack($records, 'pl', FixtureFactory::learningPack($records));
        $resolver = new PackResolver(
            new InMemoryPackCatalog([$pack]),
            new ManifestCompatibilityValidator(new RuntimeContractVersions(clues: '0.0.1')),
        );

        $this->expectException(PackResolutionFailed::class);
        $this->expectExceptionMessage('learning pack requires clues contract 0.1.0 or newer');
        $resolver->resolve(FixtureFactory::request('pl', ClueMode::Learning, CefrLevel::A1));
    }

    public function testGlobalDurationExpiryPreventsAnotherFallbackRun(): void
    {
        $clock = new class implements ClockInterface {
            /** @var list<int> */
            private array $times = [0, 0, 1_000_000];

            public function monotonicNanoseconds(): int
            {
                return array_shift($this->times) ?? 1_000_000;
            }
        };
        $generator = new ScenarioGeneratorFactory([GenerationStatus::Unsatisfiable, GenerationStatus::Success]);
        $policy = new FailurePolicy([FallbackStep::retry(), FallbackStep::fail()]);
        $request = FixtureFactory::request(
            failurePolicy: $policy,
            workBudget: new WorkBudget(5, 10, 1_000, 100, 1),
        );

        $result = FixtureFactory::builder(
            [FixtureFactory::pack(FixtureFactory::records())],
            $generator,
            new TestClueAssigner(),
            new TestHistory(UsageHistorySnapshot::available()),
            $clock,
        )->build($request, new IdempotencyKey('duration-case'), new NeverCancelled());

        self::assertSame('global_work_budget_exhausted', $result->failure?->code);
        self::assertCount(1, $generator->requests);
    }

    public function testProgrammingErrorsFromGenerationAreNotConvertedToDomainFailures(): void
    {
        $builder = FixtureFactory::builder(
            [FixtureFactory::pack(FixtureFactory::records())],
            new ScenarioGeneratorFactory(throwable: new \TypeError('programming defect')),
            new TestClueAssigner(),
            new TestHistory(UsageHistorySnapshot::available()),
        );

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('programming defect');
        $builder->build(FixtureFactory::request(), new IdempotencyKey('error-case'), new NeverCancelled());
    }

    public function testMissingAndIncompatiblePacksReturnStructuredFailures(): void
    {
        $request = FixtureFactory::request('pl', ClueMode::Learning, CefrLevel::A1);
        $missing = FixtureFactory::builder([], new ScenarioGeneratorFactory(), new TestClueAssigner(), new TestHistory(UsageHistorySnapshot::available()))
            ->build($request, new IdempotencyKey('missing-pack'), new NeverCancelled());
        $records = FixtureFactory::records();
        $incompatible = FixtureFactory::builder(
            [FixtureFactory::pack($records, 'pl', FixtureFactory::learningPack($records, false))],
            new ScenarioGeneratorFactory(),
            new TestClueAssigner(),
            new TestHistory(UsageHistorySnapshot::available()),
        )->build($request, new IdempotencyKey('bad-pack'), new NeverCancelled());

        self::assertSame('pack_resolution_failed', $missing->failure?->code);
        self::assertSame('pack_resolution_failed', $incompatible->failure?->code);
    }

    public function testCompatibleBilingualFixtureBuildsAndRecentUseIsApplied(): void
    {
        $records = FixtureFactory::records();
        $learning = FixtureFactory::learningPack($records);
        $generator = new ScenarioGeneratorFactory();
        $history = new TestHistory(UsageHistorySnapshot::available([$records[1]->key]));
        $result = FixtureFactory::builder([FixtureFactory::pack($records, 'pl', $learning)], $generator, new TestClueAssigner(), $history)
            ->build(FixtureFactory::request('pl', ClueMode::Learning, CefrLevel::A1), new IdempotencyKey('bilingual-case'), new NeverCancelled());

        self::assertTrue($result->succeeded());
        self::assertSame('builder.test.en-from-pl', $result->versions?->learningPackId);
        self::assertCount(1, $history->queries);
        self::assertSame(
            [$records[1]->key->coreKey->value],
            array_map(static fn($key): string => $key->coreKey->value, $generator->requests[0]->constraints->excludedAnswerKeys()),
        );
    }

    public function testUnavailableHistoryIsNeverSilentlyEmpty(): void
    {
        $records = FixtureFactory::records();
        $failed = FixtureFactory::builder([FixtureFactory::pack($records)], new ScenarioGeneratorFactory(), new TestClueAssigner(), new TestHistory(UsageHistorySnapshot::unavailable()))
            ->build(FixtureFactory::request(), new IdempotencyKey('history-fail'), new NeverCancelled());
        $proceeded = FixtureFactory::builder([FixtureFactory::pack($records)], new ScenarioGeneratorFactory(), new TestClueAssigner(), new TestHistory(UsageHistorySnapshot::unavailable()))
            ->build(FixtureFactory::request(missingHistory: MissingHistoryPolicy::ProceedWithWarning), new IdempotencyKey('history-warn'), new NeverCancelled());

        self::assertSame('usage_history_unavailable', $failed->failure?->code);
        self::assertTrue($proceeded->succeeded());
        self::assertSame(['usage_history_unavailable_proceeded_by_policy'], $proceeded->warnings());
    }
}
