<?php

declare(strict_types=1);

namespace Crosseno\Builder\Request;

use Crosseno\Builder\History\MissingHistoryPolicy;
use Crosseno\Builder\History\RecentUsePolicy;
use Crosseno\Builder\Policy\FailurePolicy;
use Crosseno\Builder\Policy\FallbackStep;
use Crosseno\Builder\Policy\WorkBudget;
use Crosseno\Core\Grid\GridDimensions;
use Crosseno\Core\ResourceLimits;
use Crosseno\Core\Validation\AdjacencyPolicy;
use Crosseno\Core\Validation\ConnectivityPolicy;
use Crosseno\Core\Validation\DuplicateAnswerPolicy;
use Crosseno\Core\Validation\SymmetryPolicy;
use Crosseno\Core\Validation\UncheckedCellPolicy;
use Crosseno\Core\Validation\ValidationProfile;
use Crosseno\Generator\Seed\GenerationSeed;
use Crosseno\Generator\Strategy\GenerationStrategy;
use Crosseno\Learning\Model\CefrLevel;
use Crosseno\Lexicon\Candidate\Theme;
use Crosseno\Lexicon\Identity\StableAnswerKey;
use Crosseno\Lexicon\Language\LanguageCode;

final readonly class StandardBuildRequestFactory
{
    public const PROFILE_ID = 'crosseno-standard-build-request-v1';

    private RecentUsePolicy $recentUse;
    private FailurePolicy $failurePolicy;
    private WorkBudget $workBudget;
    private ValidationProfile $validationProfile;
    private ResourceLimits $resourceLimits;

    public function __construct(
        private GenerationStrategy $strategy = GenerationStrategy::Balanced,
        private BuildDifficulty $difficulty = BuildDifficulty::Medium,
        ?RecentUsePolicy $recentUse = null,
        private int $qualityThreshold = 600_000,
        ?FailurePolicy $failurePolicy = null,
        ?WorkBudget $workBudget = null,
        private int $minimumEntryLength = 3,
        ?ValidationProfile $validationProfile = null,
        ?ResourceLimits $resourceLimits = null,
    ) {
        $this->recentUse = $recentUse ?? new RecentUsePolicy(0, MissingHistoryPolicy::ProceedWithWarning);
        $this->failurePolicy = $failurePolicy ?? new FailurePolicy([
            FallbackStep::retry(),
            FallbackStep::strategy(GenerationStrategy::Fast),
            FallbackStep::fail(),
        ]);
        $this->workBudget = $workBudget ?? new WorkBudget(3, 64, 500_000, 100_000);
        $this->validationProfile = $validationProfile ?? new ValidationProfile(
            $minimumEntryLength,
            ConnectivityPolicy::RequireConnected,
            DuplicateAnswerPolicy::Forbid,
            UncheckedCellPolicy::Allow,
            SymmetryPolicy::None,
            AdjacencyPolicy::ForbidUnsharedOrthogonalTouching,
        );
        $this->resourceLimits = $resourceLimits ?? ResourceLimits::standard();
    }

    /**
     * @param list<Theme> $themes
     * @param list<StableAnswerKey> $excludedAnswerKeys
     */
    public function create(
        LanguageCode $answerLanguage,
        LanguageCode $clueLanguage,
        GridDimensions $dimensions,
        GenerationSeed $seed,
        ClueMode $clueMode = ClueMode::Standard,
        ?CefrLevel $proficiency = null,
        array $themes = [],
        array $excludedAnswerKeys = [],
        ?int $maximumEntryLength = null,
    ): BuildRequest {
        return new BuildRequest(
            $answerLanguage,
            $clueLanguage,
            $dimensions,
            $this->strategy,
            $this->difficulty,
            $clueMode,
            $proficiency,
            $seed,
            $themes,
            $excludedAnswerKeys,
            $this->recentUse,
            $this->qualityThreshold,
            $this->failurePolicy,
            $this->workBudget,
            $this->minimumEntryLength,
            $maximumEntryLength ?? max($dimensions->rows, $dimensions->columns),
            $this->validationProfile,
            $this->resourceLimits,
        );
    }
}
