<?php

declare(strict_types=1);

namespace Crosseno\Builder\Tests\Support;

use Crosseno\Builder\Contract\GeneratorFactoryInterface;
use Crosseno\Builder\Contract\UsageHistoryInterface;
use Crosseno\Builder\CrosswordBuilder;
use Crosseno\Builder\Generation\DefaultGeneratorFactory;
use Crosseno\Builder\Generation\EligibilityBuilder;
use Crosseno\Builder\History\MissingHistoryPolicy;
use Crosseno\Builder\History\RecentUsePolicy;
use Crosseno\Builder\History\UsageHistoryQuery;
use Crosseno\Builder\History\UsageHistorySnapshot;
use Crosseno\Builder\Pack\InMemoryPackCatalog;
use Crosseno\Builder\Pack\PackDescriptor;
use Crosseno\Builder\Pack\PackResolver;
use Crosseno\Builder\Policy\FailurePolicy;
use Crosseno\Builder\Policy\WorkBudget;
use Crosseno\Builder\Quality\StandardQualityEvaluator;
use Crosseno\Builder\Request\BuildDifficulty;
use Crosseno\Builder\Request\BuildRequest;
use Crosseno\Builder\Request\ClueMode;
use Crosseno\Clues\Assignment\ClueAssignment;
use Crosseno\Clues\Assignment\ClueAssignmentConstraints;
use Crosseno\Clues\Assignment\ClueAssignmentResult;
use Crosseno\Clues\Assignment\ClueSeed;
use Crosseno\Clues\Assignment\ClueSet;
use Crosseno\Clues\Contract\ClueAssignerInterface;
use Crosseno\Clues\Contract\ClueProviderInterface;
use Crosseno\Clues\Exception\ClueAssignmentFailed;
use Crosseno\Clues\InMemory\InMemoryClueCatalog;
use Crosseno\Clues\Model\Clue;
use Crosseno\Clues\Model\ClueDifficulty;
use Crosseno\Clues\Model\ClueId;
use Crosseno\Clues\Model\ClueType;
use Crosseno\Core\Answer\Answer;
use Crosseno\Core\Crossword\Crossword;
use Crosseno\Core\Crossword\CrosswordEntry;
use Crosseno\Core\Crossword\DuplicatePlacementPolicy;
use Crosseno\Core\Grid\CellSymbol;
use Crosseno\Core\Grid\Direction;
use Crosseno\Core\Grid\Grid;
use Crosseno\Core\Grid\GridDimensions;
use Crosseno\Core\Grid\Placement;
use Crosseno\Core\Grid\Position;
use Crosseno\Core\ResourceLimits;
use Crosseno\Core\Validation\ValidationProfile;
use Crosseno\Generator\Budget\CancellationTokenInterface;
use Crosseno\Generator\Contract\LayoutGeneratorInterface;
use Crosseno\Generator\Request\GenerationMode;
use Crosseno\Generator\Request\GenerationRequest;
use Crosseno\Generator\Result\GenerationFailure;
use Crosseno\Generator\Result\GenerationMetadata;
use Crosseno\Generator\Result\GenerationResult;
use Crosseno\Generator\Result\GenerationStatus;
use Crosseno\Generator\Score\FixedScore;
use Crosseno\Generator\Score\GenerationScores;
use Crosseno\Generator\Seed\GenerationSeed;
use Crosseno\Generator\Strategy\GenerationStrategy;
use Crosseno\Generator\Strategy\StrategyCatalog;
use Crosseno\Generator\Time\ClockInterface;
use Crosseno\Generator\Time\SystemClock;
use Crosseno\Learning\Contract\CoverageIndexInterface;
use Crosseno\Learning\Contract\LearningCatalogInterface;
use Crosseno\Learning\Contract\LearningPackInterface;
use Crosseno\Learning\Coverage\CoverageCompatibility;
use Crosseno\Learning\Coverage\CoverageMask;
use Crosseno\Learning\Manifest\LearningPackManifest;
use Crosseno\Learning\Model\Ambiguity;
use Crosseno\Learning\Model\CefrLevel;
use Crosseno\Learning\Model\Confidence;
use Crosseno\Learning\Model\CoverageQuery;
use Crosseno\Learning\Model\LanguagePair;
use Crosseno\Learning\Model\LearningClue;
use Crosseno\Lexicon\Candidate\AnswerClass;
use Crosseno\Lexicon\Candidate\Difficulty;
use Crosseno\Lexicon\Candidate\TriStateFlag;
use Crosseno\Lexicon\Catalog\LexemeRecord;
use Crosseno\Lexicon\Catalog\SenseRecord;
use Crosseno\Lexicon\Catalog\SourceRecord;
use Crosseno\Lexicon\Contract\LanguagePackInterface;
use Crosseno\Lexicon\Contract\LexiconInterface;
use Crosseno\Lexicon\Contract\RichLexicalCatalogInterface;
use Crosseno\Lexicon\Contract\RuntimeLanguagePackInterface;
use Crosseno\Lexicon\Contract\SolverIndexInterface;
use Crosseno\Lexicon\Identity\LexemeKey;
use Crosseno\Lexicon\Identity\StableAnswerKey;
use Crosseno\Lexicon\Identity\StableKeyAlgorithmVersion;
use Crosseno\Lexicon\Identity\StableKeyFactory;
use Crosseno\Lexicon\Identity\StableSenseKey;
use Crosseno\Lexicon\InMemory\InMemoryLexicon;
use Crosseno\Lexicon\Language\AnswerNormalizerInterface;
use Crosseno\Lexicon\Language\CellTokenizerInterface;
use Crosseno\Lexicon\Language\LanguageCode;
use Crosseno\Lexicon\Language\LexicalEquivalenceInterface;
use Crosseno\Lexicon\Language\Rfc4647LanguageMatcher;
use Crosseno\Lexicon\Manifest\LanguagePackManifest;
use Crosseno\Lexicon\Manifest\LanguagePackMetadata;
use Crosseno\Lexicon\Record\AnswerProfile;
use Crosseno\Lexicon\Record\AnswerRecord;
use Crosseno\Lexicon\Runtime\ClueCoverageMetadata;
use Crosseno\Lexicon\Runtime\RuntimePackIdentity;

final class FixtureFactory
{
    /** @return list<AnswerRecord> */
    public static function records(): array
    {
        return [self::record('CAT', ['C', 'A', 'T']), self::record('ART', ['A', 'R', 'T'])];
    }

    /** @param non-empty-list<string> $cells */
    public static function record(string $id, array $cells): AnswerRecord
    {
        $keys = new StableKeyFactory();
        $version = StableKeyAlgorithmVersion::v1();
        $key = $keys->answer('builder-test', [$id], $version);

        return new AnswerRecord(
            $key,
            new Answer($key->coreKey, array_map(static fn(string $cell): CellSymbol => new CellSymbol($cell), $cells), $id, ResourceLimits::standard()),
            $keys->lexeme('builder-test', [$id], $version),
            [],
            new AnswerProfile(
                new Difficulty(30),
                [new AnswerClass('standard')],
                TriStateFlag::No,
                TriStateFlag::No,
                [],
                [new LanguageCode('en'), new LanguageCode('pl')],
                [],
                100_000,
            ),
        );
    }

    /** @param list<AnswerRecord> $records */
    public static function languagePack(array $records, bool $compatibleRuntimeIdentity = true): TestLanguagePack
    {
        $metadata = new LanguagePackMetadata('builder.test.en', new LanguageCode('en'), '2026.07.1', 'nfc-v1', 'cells-v1', StableKeyAlgorithmVersion::v1());
        $stableKeyDigest = hash('sha256', implode("\n", array_map(
            static fn(AnswerRecord $record): string => $record->key->coreKey->value,
            $records,
        )));
        $manifest = new LanguagePackManifest($metadata, '0.1.0', '0.1.0', '0.1.0', [], \count($records), 0, [], $stableKeyDigest, 'builder-test-space');

        return new TestLanguagePack($metadata, $manifest, new InMemoryLexicon($records), $records, $compatibleRuntimeIdentity);
    }

    /** @param list<AnswerRecord> $records */
    public static function pack(array $records, string $clueLanguage = 'en', ?LearningPackInterface $learning = null): PackDescriptor
    {
        return new PackDescriptor(
            self::languagePack($records),
            new LanguageCode($clueLanguage),
            'builder.test.clues.' . $clueLanguage,
            '2026.07.1',
            new InMemoryClueCatalog([], new Rfc4647LanguageMatcher()),
            array_map(static fn(AnswerRecord $record): StableAnswerKey => $record->key, $records),
            $learning,
        );
    }

    /** @param list<AnswerRecord> $records */
    public static function learningPack(array $records, bool $compatible = true): TestLearningPack
    {
        $answerManifest = self::languagePack($records)->manifest();
        $compatibility = $compatible
            ? CoverageCompatibility::fromAnswerPack($answerManifest)
            : new CoverageCompatibility(str_repeat('b', 64), $answerManifest->metadata->tokenizationProfileId, $answerManifest->ordinalSpaceId);
        $manifest = new LearningPackManifest(
            'builder.test.en-from-pl',
            '2026.07.1',
            new LanguagePair(new LanguageCode('en'), new LanguageCode('pl')),
            'Fixture CEFR rubric.',
            '0.1.0',
            '0.1.0',
            '0.1.0',
            '0.1.0',
            \count($records),
            $compatibility,
        );
        $clues = [];
        foreach ($records as $index => $record) {
            $clues[] = new LearningClue(
                new ClueId('learning-' . $index),
                $record->key,
                $manifest->languages,
                CefrLevel::A1,
                ClueType::Translation,
                null,
                new Confidence(10_000),
                new Ambiguity(0),
            );
        }

        return new TestLearningPack($manifest, $clues, \count($records));
    }

    public static function request(
        string $clueLanguage = 'en',
        ClueMode $mode = ClueMode::Standard,
        ?CefrLevel $proficiency = null,
        ?FailurePolicy $failurePolicy = null,
        int $qualityThreshold = 700_000,
        MissingHistoryPolicy $missingHistory = MissingHistoryPolicy::Fail,
        ?WorkBudget $workBudget = null,
    ): BuildRequest {
        return new BuildRequest(
            new LanguageCode('en'),
            new LanguageCode($clueLanguage),
            new GridDimensions(5, 5),
            GenerationStrategy::Balanced,
            BuildDifficulty::Easy,
            $mode,
            $proficiency,
            new GenerationSeed('0123456789abcdef'),
            [],
            [],
            new RecentUsePolicy(5, $missingHistory),
            $qualityThreshold,
            $failurePolicy ?? FailurePolicy::fail(),
            $workBudget ?? new WorkBudget(5, 10, 1_000, 100),
            3,
            5,
            ValidationProfile::permissive(),
            ResourceLimits::standard(),
        );
    }

    /** @param list<PackDescriptor> $packs */
    public static function builder(
        array $packs,
        ScenarioGeneratorFactory $generators,
        TestClueAssigner $clues,
        TestHistory $history,
        ?ClockInterface $clock = null,
    ): CrosswordBuilder {
        return new CrosswordBuilder(
            new PackResolver(new InMemoryPackCatalog($packs)),
            new EligibilityBuilder(),
            $generators,
            $clues,
            new StandardQualityEvaluator(),
            $history,
            $clock ?? new SystemClock(),
        );
    }
}

final readonly class TestLanguagePack implements LanguagePackInterface, RuntimeLanguagePackInterface
{
    /** @param list<AnswerRecord> $records */
    public function __construct(
        private LanguagePackMetadata $metadata,
        private LanguagePackManifest $manifest,
        private LexiconInterface $lexicon,
        private array $records,
        private bool $compatibleRuntimeIdentity,
    ) {}

    public function metadata(): LanguagePackMetadata
    {
        return $this->metadata;
    }
    public function manifest(): LanguagePackManifest
    {
        return $this->manifest;
    }
    public function lexicon(): LexiconInterface
    {
        return $this->lexicon;
    }
    public function normalizer(): AnswerNormalizerInterface
    {
        return new TestLanguageServices();
    }
    public function tokenizer(): CellTokenizerInterface
    {
        return new TestLanguageServices();
    }
    public function equivalence(): LexicalEquivalenceInterface
    {
        return new TestLanguageServices();
    }
    public function catalog(): RichLexicalCatalogInterface
    {
        return new TestRichCatalog($this->lexicon, $this->manifest->recordCount);
    }
    public function solverIndex(): SolverIndexInterface
    {
        return $this->lexicon;
    }
    public function answerKeysByOrdinal(): array
    {
        return array_map(static fn(AnswerRecord $record): StableAnswerKey => $record->key, $this->records);
    }
    public function runtimeIdentity(): RuntimePackIdentity
    {
        return new RuntimePackIdentity(
            $this->metadata->packId,
            $this->metadata->dataVersion,
            $this->compatibleRuntimeIdentity ? $this->manifest->stableKeyDigest : str_repeat('b', 64),
            $this->manifest->ordinalSpaceId,
            [],
        );
    }
    public function clueCoverage(): ClueCoverageMetadata
    {
        return new ClueCoverageMetadata(['en' => $this->manifest->recordCount]);
    }
}

final readonly class TestRichCatalog implements RichLexicalCatalogInterface
{
    public function __construct(private LexiconInterface $lexicon, private int $recordCount) {}

    public function answer(StableAnswerKey $key): ?AnswerRecord
    {
        return $this->lexicon->answer($key);
    }
    public function answersForLexeme(LexemeKey $key): array
    {
        return $this->lexicon->answersForLexeme($key);
    }
    public function answersForSense(StableSenseKey $key): array
    {
        return $this->lexicon->answersForSense($key);
    }
    public function cluesForSense(StableSenseKey $key, ?LanguageCode $language = null): array
    {
        return [];
    }
    public function clueCoverage(): ClueCoverageMetadata
    {
        return new ClueCoverageMetadata(['en' => $this->recordCount]);
    }
    public function lexeme(LexemeKey $key): ?LexemeRecord
    {
        return null;
    }
    public function sense(StableSenseKey $key): ?SenseRecord
    {
        return null;
    }
    public function topicsForSense(StableSenseKey $key): array
    {
        return [];
    }
    public function source(string $id): ?SourceRecord
    {
        return null;
    }
}

final readonly class TestLanguageServices implements AnswerNormalizerInterface, CellTokenizerInterface, LexicalEquivalenceInterface
{
    public function profileId(): string
    {
        return 'fixture-v1';
    }
    public function normalize(string $answer): string
    {
        return strtoupper($answer);
    }
    public function tokenize(string $normalizedAnswer): array
    {
        return array_map(static fn(string $cell): CellSymbol => new CellSymbol($cell), str_split($normalizedAnswer));
    }
    public function root(string $normalizedText): string
    {
        return $normalizedText;
    }
    public function equivalent(string $leftNormalizedText, string $rightNormalizedText): bool
    {
        return $leftNormalizedText === $rightNormalizedText;
    }
}

final class ScenarioGeneratorFactory implements GeneratorFactoryInterface
{
    /** @var list<GenerationStatus> */
    private array $statuses;

    /** @var list<GenerationRequest> */
    public array $requests = [];

    /** @param list<GenerationStatus> $statuses */
    public function __construct(
        array $statuses = [GenerationStatus::Success],
        private readonly int $score = 800_000,
        private readonly ?\Throwable $throwable = null,
    ) {
        $this->statuses = $statuses;
    }

    public function create(SolverIndexInterface $index, LanguagePackManifest $manifest): LayoutGeneratorInterface
    {
        return new class ($this) implements LayoutGeneratorInterface {
            public function __construct(private ScenarioGeneratorFactory $factory) {}

            public function generate(GenerationRequest $request, CancellationTokenInterface $cancellation): GenerationResult
            {
                $this->factory->requests[] = $request;

                return $this->factory->result($request);
            }
        };
    }

    public function result(GenerationRequest $request): GenerationResult
    {
        if ($this->throwable !== null) {
            throw $this->throwable;
        }
        $status = array_shift($this->statuses) ?? GenerationStatus::Success;
        $scores = new GenerationScores(new FixedScore($this->score), new FixedScore($this->score), new FixedScore($this->score), new FixedScore($this->score), new FixedScore($this->score));
        $metadata = new GenerationMetadata(
            '0.1.0',
            GenerationMode::FreeForm,
            $request->strategyProfile,
            'builder.test.en',
            '2026.07.1',
            new LanguageCode('en'),
            $request->clueLanguage,
            $request->seed,
            'fixture-v1',
            $request->dimensions,
            $request->budget,
            1,
            10,
            1,
            1,
            true,
            $scores,
        );
        if ($status !== GenerationStatus::Success) {
            return GenerationResult::failure(new GenerationFailure($status, 'fixture_' . $status->value, 'Fixture generation failure.'), $metadata);
        }
        $records = FixtureFactory::records();
        $limits = ResourceLimits::standard();
        $crossword = new Crossword(
            Grid::empty($request->dimensions, $limits),
            [
                new CrosswordEntry($records[0]->answer, new Placement(new Position(0, 0), Direction::Horizontal, 3, $limits)),
                new CrosswordEntry($records[1]->answer, new Placement(new Position(0, 1), Direction::Vertical, 3, $limits)),
            ],
            DuplicatePlacementPolicy::Forbid,
            $limits,
        );

        return GenerationResult::success($crossword, $metadata);
    }
}

final class TestClueAssigner implements ClueAssignerInterface
{
    public int $calls = 0;

    public function __construct(
        private readonly int $score = 10_000,
        private readonly bool $fail = false,
        private readonly string $failureMessage = 'No valid fixture clue exists.',
    ) {}

    public function assign(Crossword $crossword, ClueProviderInterface $provider, ClueAssignmentConstraints $constraints, ClueSeed $seed): ClueAssignmentResult
    {
        ++$this->calls;
        if ($this->fail) {
            throw new ClueAssignmentFailed($this->failureMessage);
        }
        $assignments = [];
        foreach ($crossword->entries() as $index => $entry) {
            $clue = new Clue(new ClueId('fixture-' . $index), 'Fixture clue ' . $index, ClueType::Definition, new ClueDifficulty($constraints->targetDifficulty), $constraints->language, null);
            $assignments[] = new ClueAssignment($index, $entry->answer->key, $clue, $this->score);
        }

        return new ClueAssignmentResult(new ClueSet($assignments), [], 'fixture-v1');
    }
}

final class TestHistory implements UsageHistoryInterface
{
    /** @var list<UsageHistoryQuery> */
    public array $queries = [];

    public function __construct(private readonly UsageHistorySnapshot $snapshot) {}

    public function lookup(UsageHistoryQuery $query): UsageHistorySnapshot
    {
        $this->queries[] = $query;

        return $this->snapshot;
    }
}

final readonly class TestLearningPack implements LearningPackInterface
{
    /** @param list<LearningClue> $clues */
    public function __construct(private LearningPackManifest $manifest, private array $clues, private int $ordinalCount) {}

    public function manifest(): LearningPackManifest
    {
        return $this->manifest;
    }

    public function catalog(): LearningCatalogInterface
    {
        return new class ($this->clues) implements LearningCatalogInterface {
            /** @param list<LearningClue> $clues */
            public function __construct(private array $clues) {}
            public function eligible(CoverageQuery $query, ?StableAnswerKey $answerKey = null): array
            {
                return array_values(array_filter($this->clues, static fn(LearningClue $clue): bool => $answerKey === null || $clue->answerKey->coreKey->value === $answerKey->coreKey->value));
            }
            public function hasApprovedClue(StableAnswerKey $answerKey, CoverageQuery $query): bool
            {
                return $this->eligible($query, $answerKey) !== [];
            }
        };
    }

    public function coverageIndex(): CoverageIndexInterface
    {
        return new class ($this->manifest->answerPack, $this->ordinalCount) implements CoverageIndexInterface {
            public function __construct(private CoverageCompatibility $compatibility, private int $count) {}
            public function query(CoverageQuery $query): CoverageMask
            {
                return new CoverageMask($this->compatibility, $this->count, range(0, $this->count - 1));
            }
        };
    }
}
