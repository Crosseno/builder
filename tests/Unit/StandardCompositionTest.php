<?php

declare(strict_types=1);

namespace Crosseno\Builder\Tests\Unit;

use Crosseno\Builder\Contract\QualityEvaluatorInterface;
use Crosseno\Builder\Exception\InvalidBuildRequest;
use Crosseno\Builder\History\MissingHistoryPolicy;
use Crosseno\Builder\History\RecentUsePolicy;
use Crosseno\Builder\History\UsageHistorySnapshot;
use Crosseno\Builder\Pack\ManifestCompatibilityValidator;
use Crosseno\Builder\Pack\PackDescriptor;
use Crosseno\Builder\Pack\RuntimeContractVersions;
use Crosseno\Builder\Policy\FailurePolicy;
use Crosseno\Builder\Policy\WorkBudget;
use Crosseno\Builder\Quality\PublicationQuality;
use Crosseno\Builder\Request\BuildDifficulty;
use Crosseno\Builder\Request\IdempotencyKey;
use Crosseno\Builder\Request\StandardBuildRequestFactory;
use Crosseno\Builder\StandardBuilderFactory;
use Crosseno\Builder\Tests\Support\FixtureFactory;
use Crosseno\Builder\Tests\Support\ScenarioGeneratorFactory;
use Crosseno\Builder\Tests\Support\TestClueAssigner;
use Crosseno\Builder\Tests\Support\TestHistory;
use Crosseno\Clues\Catalog\LexicalCatalogClueProvider;
use Crosseno\Clues\Query\ClueQuery;
use Crosseno\Core\Answer\SenseKey;
use Crosseno\Core\Grid\GridDimensions;
use Crosseno\Core\ResourceLimits;
use Crosseno\Generator\Seed\GenerationSeed;
use Crosseno\Generator\Strategy\GenerationStrategy;
use Crosseno\Generator\Time\ClockInterface;
use Crosseno\Lexicon\Identity\StableKeyAlgorithmVersion;
use Crosseno\Lexicon\Language\LanguageCode;
use Crosseno\Lexicon\Language\LanguageMatchingPolicy;
use PHPUnit\Framework\TestCase;

final class StandardCompositionTest extends TestCase
{
    public function testStandardRequestDefaultsAreVersionedAndReplaceable(): void
    {
        $language = new LanguageCode('en');
        $standard = (new StandardBuildRequestFactory())->create(
            $language,
            $language,
            new GridDimensions(9, 9),
            GenerationSeed::fromInteger(7),
        );
        $custom = (new StandardBuildRequestFactory(
            strategy: GenerationStrategy::Dense,
            difficulty: BuildDifficulty::Hard,
            recentUse: new RecentUsePolicy(12, MissingHistoryPolicy::Fail),
            qualityThreshold: 750_000,
            failurePolicy: FailurePolicy::fail(),
            workBudget: new WorkBudget(1, 2, 3, 4),
            minimumEntryLength: 4,
            resourceLimits: ResourceLimits::standard(),
        ))->create(
            $language,
            $language,
            new GridDimensions(9, 9),
            GenerationSeed::fromInteger(8),
            maximumEntryLength: 7,
        );

        self::assertSame('crosseno-standard-build-request-v1', StandardBuildRequestFactory::PROFILE_ID);
        self::assertSame(GenerationStrategy::Balanced, $standard->strategy);
        self::assertSame(BuildDifficulty::Medium, $standard->difficulty);
        self::assertSame(600_000, $standard->qualityThreshold);
        self::assertSame(3, $standard->workBudget->maximumRuns);
        self::assertSame(GenerationStrategy::Dense, $custom->strategy);
        self::assertSame(BuildDifficulty::Hard, $custom->difficulty);
        self::assertSame(12, $custom->recentUse->maximumBuilds);
        self::assertSame(750_000, $custom->qualityThreshold);
        self::assertSame(7, $custom->maximumEntryLength);
    }

    public function testStandardBuilderCompositionUsesInjectedOverrides(): void
    {
        $records = FixtureFactory::records();
        $runtime = FixtureFactory::languagePack($records);
        $generators = new ScenarioGeneratorFactory();
        $assigner = new TestClueAssigner();
        $history = new TestHistory(UsageHistorySnapshot::available());
        $clock = new class implements ClockInterface {
            public int $calls = 0;
            public function monotonicNanoseconds(): int
            {
                ++$this->calls;

                return 0;
            }
        };
        $quality = new class implements QualityEvaluatorInterface {
            public int $calls = 0;
            public function evaluate(\Crosseno\Generator\Result\GenerationMetadata $generation, \Crosseno\Clues\Assignment\ClueAssignmentResult $clues, int $expectedClues): PublicationQuality
            {
                ++$this->calls;

                return new PublicationQuality(800_000, 800_000, 800_000, 1_000_000, 800_000);
            }
        };
        $factory = new StandardBuilderFactory(
            $generators,
            $assigner,
            $quality,
            $history,
            $clock,
            new ManifestCompatibilityValidator(new RuntimeContractVersions()),
        );
        $provider = new \Crosseno\Clues\InMemory\InMemoryClueCatalog([], new \Crosseno\Lexicon\Language\Rfc4647LanguageMatcher());
        $result = $factory->create(
            $runtime,
            $provider,
            new LanguageCode('en'),
        )->build(FixtureFactory::request(), new IdempotencyKey('override-proof'), StandardBuilderFactory::synchronousCancellation());

        self::assertTrue($result->succeeded());
        self::assertCount(1, $generators->requests);
        self::assertCount(1, $history->queries);
        self::assertSame(1, $assigner->calls);
        self::assertSame(1, $quality->calls);
        self::assertGreaterThan(0, $clock->calls);
    }

    public function testCompatibilityAndOrdinalIdentityOverridesFailExplicitly(): void
    {
        $records = FixtureFactory::records();
        $runtime = FixtureFactory::languagePack($records);
        $provider = new \Crosseno\Clues\InMemory\InMemoryClueCatalog([], new \Crosseno\Lexicon\Language\Rfc4647LanguageMatcher());
        $builder = (new StandardBuilderFactory(
            compatibility: new ManifestCompatibilityValidator(new RuntimeContractVersions(core: '0.0.1')),
        ))->create($runtime, $provider, new LanguageCode('en'));
        $result = $builder->build(FixtureFactory::request(), new IdempotencyKey('compatibility-proof'), StandardBuilderFactory::synchronousCancellation());

        self::assertSame('pack_resolution_failed', $result->failure?->code);

        $this->expectException(InvalidBuildRequest::class);
        $this->expectExceptionMessage('artifact and ordinal-space identity');
        PackDescriptor::fromRuntimePack(
            FixtureFactory::languagePack($records, false),
            new LanguageCode('en'),
            $provider,
        );
    }

    public function testLexicalClueAdapterReturnsDeterministicEmptyResultForMissingSense(): void
    {
        $runtime = FixtureFactory::languagePack(FixtureFactory::records());
        $provider = new LexicalCatalogClueProvider($runtime->catalog(), StableKeyAlgorithmVersion::v1());
        $query = new ClueQuery(
            new SenseKey('xk1:sense:builder-test:' . str_repeat('a', 64)),
            new LanguageCode('en'),
            LanguageMatchingPolicy::Exact,
            maximumResults: 1,
        );

        self::assertSame([], [...$provider->provide($query)]);
        self::assertSame([], [...$provider->provide($query)]);
    }
}
