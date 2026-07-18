<?php

declare(strict_types=1);

namespace Crosseno\Builder\Request;

use Crosseno\Builder\Exception\InvalidBuildRequest;
use Crosseno\Builder\History\RecentUsePolicy;
use Crosseno\Builder\Policy\FailurePolicy;
use Crosseno\Builder\Policy\WorkBudget;
use Crosseno\Core\Grid\GridDimensions;
use Crosseno\Core\ResourceLimits;
use Crosseno\Core\Validation\ValidationProfile;
use Crosseno\Generator\Seed\GenerationSeed;
use Crosseno\Generator\Strategy\GenerationStrategy;
use Crosseno\Learning\Model\CefrLevel;
use Crosseno\Lexicon\Candidate\Theme;
use Crosseno\Lexicon\Identity\StableAnswerKey;
use Crosseno\Lexicon\Language\LanguageCode;

final readonly class BuildRequest implements \JsonSerializable
{
    /** @var list<Theme> */
    private array $themes;

    /** @var list<StableAnswerKey> */
    private array $excludedAnswerKeys;

    /**
     * @param list<Theme> $themes
     * @param list<StableAnswerKey> $excludedAnswerKeys
     */
    public function __construct(
        public LanguageCode $answerLanguage,
        public LanguageCode $clueLanguage,
        public GridDimensions $dimensions,
        public GenerationStrategy $strategy,
        public BuildDifficulty $difficulty,
        public ClueMode $clueMode,
        public ?CefrLevel $proficiency,
        public GenerationSeed $seed,
        array $themes,
        array $excludedAnswerKeys,
        public RecentUsePolicy $recentUse,
        public int $qualityThreshold,
        public FailurePolicy $failurePolicy,
        public WorkBudget $workBudget,
        public int $minimumEntryLength,
        public int $maximumEntryLength,
        public ValidationProfile $validationProfile,
        public ResourceLimits $resourceLimits,
    ) {
        $resourceLimits->assertDimensions($dimensions);
        if (($clueMode === ClueMode::Learning) !== ($proficiency instanceof CefrLevel)) {
            throw new InvalidBuildRequest('Learning clue mode requires proficiency; standard mode forbids it.');
        }
        if ($qualityThreshold < 0 || $qualityThreshold > 1_000_000) {
            throw new InvalidBuildRequest('Quality threshold must be integer millionths from 0 through 1,000,000.');
        }
        if ($minimumEntryLength < 1 || $maximumEntryLength < $minimumEntryLength
            || $maximumEntryLength > max($dimensions->rows, $dimensions->columns)) {
            throw new InvalidBuildRequest('Entry-length bounds are invalid for the requested dimensions.');
        }
        $resourceLimits->assertEntryLength($maximumEntryLength);
        $this->themes = self::normalizeThemes($themes);
        $this->excludedAnswerKeys = self::normalizeAnswerKeys($excludedAnswerKeys);
    }

    /** @return list<Theme> */
    public function themes(): array
    {
        return $this->themes;
    }

    /** @return list<StableAnswerKey> */
    public function excludedAnswerKeys(): array
    {
        return $this->excludedAnswerKeys;
    }

    public function canonicalJson(): string
    {
        return json_encode($this, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function requestHash(): string
    {
        return hash('sha256', $this->canonicalJson());
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'schema' => 'crosseno-build-request-v1',
            'answer_language' => $this->answerLanguage->value,
            'clue_language' => $this->clueLanguage->value,
            'dimensions' => ['rows' => $this->dimensions->rows, 'columns' => $this->dimensions->columns],
            'strategy' => $this->strategy->value,
            'difficulty' => $this->difficulty->value,
            'clue_mode' => $this->clueMode->value,
            'proficiency' => $this->proficiency?->value,
            'seed' => $this->seed->unsignedHex,
            'themes' => array_map(static fn(Theme $theme): string => $theme->value, $this->themes),
            'excluded_answer_keys' => array_map(static fn(StableAnswerKey $key): string => $key->coreKey->value, $this->excludedAnswerKeys),
            'recent_use' => $this->recentUse,
            'quality_threshold' => $this->qualityThreshold,
            'failure_policy' => $this->failurePolicy,
            'work_budget' => $this->workBudget,
            'entry_length' => ['minimum' => $this->minimumEntryLength, 'maximum' => $this->maximumEntryLength],
            'validation_profile' => [
                'minimum_entry_length' => $this->validationProfile->minimumEntryLength,
                'connectivity' => $this->validationProfile->connectivity->value,
                'duplicate_answers' => $this->validationProfile->duplicateAnswers->value,
                'unchecked_cells' => $this->validationProfile->uncheckedCells->value,
                'symmetry' => $this->validationProfile->symmetry->value,
                'adjacency' => $this->validationProfile->adjacency->value,
            ],
            'resource_limits' => [
                'max_rows' => $this->resourceLimits->maxRows,
                'max_columns' => $this->resourceLimits->maxColumns,
                'max_grid_cells' => $this->resourceLimits->maxGridCells,
                'max_entry_length' => $this->resourceLimits->maxEntryLength,
                'max_occupied_cells' => $this->resourceLimits->maxOccupiedCells,
                'max_snapshot_bytes' => $this->resourceLimits->maxSnapshotBytes,
            ],
        ];
    }

    /**
     * @param list<Theme> $values
     * @return list<Theme>
     */
    private static function normalizeThemes(array $values): array
    {
        if (!array_is_list($values)) {
            throw new InvalidBuildRequest('Themes must be a list.');
        }
        $result = [];
        foreach ($values as $value) {
            if (!$value instanceof Theme) {
                throw new InvalidBuildRequest('Theme list contains an invalid value.');
            }
            $result[$value->value] = $value;
        }
        ksort($result, SORT_STRING);

        return array_values($result);
    }

    /**
     * @param list<StableAnswerKey> $values
     * @return list<StableAnswerKey>
     */
    private static function normalizeAnswerKeys(array $values): array
    {
        if (!array_is_list($values)) {
            throw new InvalidBuildRequest('Excluded answer keys must be a list.');
        }
        $result = [];
        foreach ($values as $value) {
            if (!$value instanceof StableAnswerKey) {
                throw new InvalidBuildRequest('Excluded answer-key list contains an invalid value.');
            }
            $result[$value->coreKey->value] = $value;
        }
        ksort($result, SORT_STRING);

        return array_values($result);
    }
}
