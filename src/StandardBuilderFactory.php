<?php

declare(strict_types=1);

namespace Crosseno\Builder;

use Crosseno\Builder\Contract\GeneratorFactoryInterface;
use Crosseno\Builder\Contract\QualityEvaluatorInterface;
use Crosseno\Builder\Contract\UsageHistoryInterface;
use Crosseno\Builder\Exception\InvalidBuildRequest;
use Crosseno\Builder\Generation\DefaultGeneratorFactory;
use Crosseno\Builder\Generation\EligibilityBuilder;
use Crosseno\Builder\History\NullUsageHistory;
use Crosseno\Builder\Pack\InMemoryPackCatalog;
use Crosseno\Builder\Pack\ManifestCompatibilityValidator;
use Crosseno\Builder\Pack\PackDescriptor;
use Crosseno\Builder\Pack\PackResolver;
use Crosseno\Builder\Quality\StandardQualityEvaluator;
use Crosseno\Clues\Assignment\DeterministicClueAssigner;
use Crosseno\Clues\Contract\ClueAssignerInterface;
use Crosseno\Clues\Contract\ClueProviderInterface;
use Crosseno\Clues\Contract\LanguageServiceProviderInterface;
use Crosseno\Clues\Scoring\DifficultyClueScorer;
use Crosseno\Clues\Selection\DeterministicClueSelector;
use Crosseno\Clues\Validation\CompositeClueValidator;
use Crosseno\Clues\Validation\DifficultyClueValidator;
use Crosseno\Clues\Validation\InMemoryLanguageServiceProvider;
use Crosseno\Clues\Validation\LanguageClueValidator;
use Crosseno\Clues\Validation\LeakageClueValidator;
use Crosseno\Clues\Validation\LeakageLanguageServices;
use Crosseno\Clues\Validation\LengthClueValidator;
use Crosseno\Clues\Validation\SenseClueValidator;
use Crosseno\Clues\Validation\StandardClueSetValidator;
use Crosseno\Generator\Budget\CancellationTokenInterface;
use Crosseno\Generator\Budget\NeverCancelled;
use Crosseno\Generator\Random\Sha256CounterRandomizerFactory;
use Crosseno\Generator\Score\DeterministicCrosswordScorer;
use Crosseno\Generator\Time\ClockInterface;
use Crosseno\Generator\Time\SystemClock;
use Crosseno\Learning\Contract\LearningPackInterface;
use Crosseno\Lexicon\Contract\RuntimeLanguagePackInterface;
use Crosseno\Lexicon\Language\LanguageCode;
use Crosseno\Lexicon\Language\Rfc4647LanguageMatcher;

final readonly class StandardBuilderFactory
{
    public const PROFILE_ID = 'crosseno-standard-builder-v1';

    private GeneratorFactoryInterface $generators;
    private ?ClueAssignerInterface $clueAssigner;
    private QualityEvaluatorInterface $quality;
    private UsageHistoryInterface $history;
    private ClockInterface $clock;
    private ManifestCompatibilityValidator $compatibility;
    private ?LanguageServiceProviderInterface $languageServices;

    public function __construct(
        ?GeneratorFactoryInterface $generators = null,
        ?ClueAssignerInterface $clueAssigner = null,
        ?QualityEvaluatorInterface $quality = null,
        ?UsageHistoryInterface $history = null,
        ?ClockInterface $clock = null,
        ?ManifestCompatibilityValidator $compatibility = null,
        ?LanguageServiceProviderInterface $languageServices = null,
    ) {
        $this->clock = $clock ?? new SystemClock();
        $this->generators = $generators ?? new DefaultGeneratorFactory(
            new Sha256CounterRandomizerFactory(),
            new DeterministicCrosswordScorer(),
            $this->clock,
        );
        $this->clueAssigner = $clueAssigner;
        $this->quality = $quality ?? new StandardQualityEvaluator();
        $this->history = $history ?? new NullUsageHistory();
        $this->compatibility = $compatibility ?? new ManifestCompatibilityValidator();
        $this->languageServices = $languageServices;
    }

    public function create(
        RuntimeLanguagePackInterface $runtimePack,
        ClueProviderInterface $clueProvider,
        LanguageCode $clueLanguage,
        ?string $cluePackId = null,
        ?string $cluePackVersion = null,
        ?LearningPackInterface $learningPack = null,
    ): CrosswordBuilder {
        $descriptor = PackDescriptor::fromRuntimePack(
            $runtimePack,
            $clueLanguage,
            $clueProvider,
            $cluePackId,
            $cluePackVersion,
            $learningPack,
        );

        return new CrosswordBuilder(
            new PackResolver(new InMemoryPackCatalog([$descriptor]), $this->compatibility),
            new EligibilityBuilder(),
            $this->generators,
            $this->clueAssigner ?? self::standardClueAssigner($runtimePack, $clueLanguage, $this->languageServices),
            $this->quality,
            $this->history,
            $this->clock,
            self::PROFILE_ID,
        );
    }

    public static function synchronousCancellation(): CancellationTokenInterface
    {
        return new NeverCancelled();
    }

    private static function standardClueAssigner(
        RuntimeLanguagePackInterface $runtimePack,
        LanguageCode $clueLanguage,
        ?LanguageServiceProviderInterface $languageServices,
    ): ClueAssignerInterface {
        $matcher = new Rfc4647LanguageMatcher();
        if ($languageServices === null) {
            if ($runtimePack->metadata()->answerLanguage->value !== $clueLanguage->value) {
                throw new InvalidBuildRequest('Bilingual standard composition requires clue-language leakage services or a custom clue assigner.');
            }
            $languageServices = new InMemoryLanguageServiceProvider([
                $runtimePack->metadata()->answerLanguage->value => new LeakageLanguageServices(
                    $runtimePack->normalizer(),
                    $runtimePack->equivalence(),
                ),
            ]);
        }
        if ($languageServices->forLanguage($clueLanguage) === null) {
            throw new InvalidBuildRequest('Standard composition requires leakage services for the requested clue language.');
        }

        return new DeterministicClueAssigner(
            new CompositeClueValidator([
                new LanguageClueValidator($matcher),
                new SenseClueValidator(),
                new DifficultyClueValidator(),
                new LengthClueValidator(),
                new LeakageClueValidator($languageServices),
            ]),
            new DeterministicClueSelector(new DifficultyClueScorer()),
            new StandardClueSetValidator(),
        );
    }
}
