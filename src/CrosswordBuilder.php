<?php

declare(strict_types=1);

namespace Crosseno\Builder;

use Crosseno\Builder\Contract\BuilderInterface;
use Crosseno\Builder\Contract\GeneratorFactoryInterface;
use Crosseno\Builder\Contract\QualityEvaluatorInterface;
use Crosseno\Builder\Contract\UsageHistoryInterface;
use Crosseno\Builder\Exception\BuilderException;
use Crosseno\Builder\Exception\PackResolutionFailed;
use Crosseno\Builder\Generation\EligibilityBuilder;
use Crosseno\Builder\History\MissingHistoryPolicy;
use Crosseno\Builder\History\UsageHistoryQuery;
use Crosseno\Builder\History\UsageHistorySnapshot;
use Crosseno\Builder\Pack\PackDescriptor;
use Crosseno\Builder\Pack\PackResolver;
use Crosseno\Builder\Policy\FallbackAction;
use Crosseno\Builder\Request\BuildRequest;
use Crosseno\Builder\Request\IdempotencyKey;
use Crosseno\Builder\Result\BuildFailure;
use Crosseno\Builder\Result\BuildResult;
use Crosseno\Builder\Result\BuildStatus;
use Crosseno\Builder\Result\FallbackRecord;
use Crosseno\Builder\Result\VersionSnapshot;
use Crosseno\Clues\Assignment\ClueAssignmentConstraints;
use Crosseno\Clues\Assignment\ClueSeed;
use Crosseno\Clues\Assignment\MissingCluePolicy;
use Crosseno\Clues\Contract\ClueAssignerInterface;
use Crosseno\Clues\Exception\ClueAssignmentFailed;
use Crosseno\Core\Grid\GridDimensions;
use Crosseno\Generator\Budget\CancellationTokenInterface;
use Crosseno\Generator\Budget\SearchBudget;
use Crosseno\Generator\Request\GenerationRequest;
use Crosseno\Generator\Result\GenerationMetadata;
use Crosseno\Generator\Result\GenerationStatus;
use Crosseno\Generator\Seed\GenerationSeed;
use Crosseno\Generator\Strategy\GenerationStrategy;
use Crosseno\Generator\Strategy\StrategyCatalog;
use Crosseno\Lexicon\Language\LanguageMatchingPolicy;

final readonly class CrosswordBuilder implements BuilderInterface
{
    public const VERSION = '0.1.0';
    public const KEY_ALGORITHM = 'crosseno-publication-key-v1';

    public function __construct(
        private PackResolver $packs,
        private EligibilityBuilder $eligibility,
        private GeneratorFactoryInterface $generators,
        private ClueAssignerInterface $clueAssigner,
        private QualityEvaluatorInterface $quality,
        private UsageHistoryInterface $history,
    ) {}

    public function build(BuildRequest $request, IdempotencyKey $idempotencyKey, CancellationTokenInterface $cancellation): BuildResult
    {
        $snapshot = $request->canonicalJson();
        $requestHash = hash('sha256', $snapshot);
        $publicationKey = hash('sha256', self::KEY_ALGORITHM . "\0" . $idempotencyKey->value . "\0" . $requestHash);
        $warnings = [];
        $fallbacks = [];

        try {
            $pack = $this->packs->resolve($request);
        } catch (PackResolutionFailed $exception) {
            return $this->failure(BuildStatus::Failed, $publicationKey, $snapshot, $requestHash, 'pack_resolution_failed', $exception->getMessage(), $warnings, $fallbacks);
        }
        $versions = $this->versions($pack);

        try {
            $history = $request->recentUse->maximumBuilds === 0
                ? UsageHistorySnapshot::available()
                : $this->history->lookup(new UsageHistoryQuery($request->answerLanguage, $request->clueLanguage, $request->recentUse->maximumBuilds));
        } catch (\Throwable) {
            $history = UsageHistorySnapshot::unavailable();
        }
        if (!$history->available) {
            if ($request->recentUse->missingHistory === MissingHistoryPolicy::Fail) {
                return $this->failure(BuildStatus::Failed, $publicationKey, $snapshot, $requestHash, 'usage_history_unavailable', 'Usage history is unavailable and the request requires it.', $warnings, $fallbacks, versions: $versions);
            }
            $warnings[] = 'usage_history_unavailable_proceeded_by_policy';
        }

        try {
            $eligibility = $this->eligibility->build($request, $pack, $history);
        } catch (\Throwable $exception) {
            return $this->failure(BuildStatus::Failed, $publicationKey, $snapshot, $requestHash, 'eligibility_failed', $exception->getMessage(), $warnings, $fallbacks, versions: $versions);
        }
        if ($eligibility->eligibleLearningAnswers === 0) {
            return $this->failure(BuildStatus::Failed, $publicationKey, $snapshot, $requestHash, 'empty_eligibility_mask', 'No answer has an eligible clue under the requested learning and history policy.', $warnings, $fallbacks, versions: $versions);
        }

        $strategy = $request->strategy;
        $dimensions = $request->dimensions;
        $seenConfigurations = [$this->state($strategy, $dimensions) => true];
        $run = 0;
        $remainingAttempts = $request->workBudget->maximumAttempts;
        $remainingNodes = $request->workBudget->maximumNodes;
        $remainingBacktracks = $request->workBudget->maximumBacktracks;
        $remainingDuration = $request->workBudget->maximumDurationMilliseconds;
        $stepIndex = 0;
        $lastFailure = new BuildFailure('not_started', 'No generation run was started.');
        $lastGeneration = null;
        $bestRejectedQuality = null;

        while ($run < $request->workBudget->maximumRuns && $remainingAttempts > 0 && $remainingNodes > 0) {
            $seed = $this->runSeed($request->seed, $run);
            $maximumEntryLength = min($request->maximumEntryLength, max($dimensions->rows, $dimensions->columns));
            if ($request->minimumEntryLength > $maximumEntryLength) {
                $lastFailure = new BuildFailure('invalid_fallback_dimensions', 'Fallback dimensions cannot contain the minimum entry length.');
            } else {
                $budget = new SearchBudget(
                    $remainingAttempts,
                    $remainingNodes,
                    $remainingBacktracks,
                    $remainingDuration === null ? null : max(1, $remainingDuration),
                );
                $generationRequest = new GenerationRequest(
                    $dimensions,
                    $request->minimumEntryLength,
                    $maximumEntryLength,
                    $eligibility->constraints,
                    $seed,
                    $budget,
                    StrategyCatalog::profile($strategy),
                    $request->validationProfile,
                    $request->resourceLimits,
                    $request->clueLanguage,
                );
                try {
                    $generated = $this->generators->create($eligibility->solverIndex, $pack->answerPack->manifest())->generate($generationRequest, $cancellation);
                } catch (\Throwable $exception) {
                    $lastFailure = new BuildFailure('generation_exception', $exception->getMessage());
                    $generated = null;
                }
                if ($generated !== null) {
                    $lastGeneration = $generated->metadata;
                    $remainingAttempts = max(0, $remainingAttempts - $generated->metadata->attempts);
                    $remainingNodes = max(0, $remainingNodes - $generated->metadata->exploredNodes);
                    $remainingBacktracks = max(0, $remainingBacktracks - $generated->metadata->backtracks);
                    if ($remainingDuration !== null) {
                        $remainingDuration = max(0, $remainingDuration - $generated->metadata->durationMilliseconds);
                    }
                    if ($generated->status === GenerationStatus::Interrupted) {
                        return $this->failure(BuildStatus::Failed, $publicationKey, $snapshot, $requestHash, 'generation_interrupted', 'Generation was interrupted by a wall-clock or cancellation safety limit.', $warnings, $fallbacks, $generated->metadata, $versions);
                    }
                    if ($generated->succeeded() && $generated->crossword !== null) {
                        try {
                            $clues = $this->clueAssigner->assign(
                                $generated->crossword,
                                $eligibility->clueProvider,
                                new ClueAssignmentConstraints(
                                    $request->clueLanguage,
                                    LanguageMatchingPolicy::Exact,
                                    targetDifficulty: $request->difficulty->clueTarget(),
                                    missingCluePolicy: MissingCluePolicy::FailAssignment,
                                ),
                                new ClueSeed(hash('sha256', $publicationKey . "\0" . $seed->unsignedHex . "\0" . $pack->identity())),
                            );
                            $publicationQuality = $this->quality->evaluate($generated->metadata, $clues, \count($generated->crossword->entries()));
                            if ($clues->isValid() && \count($clues->clueSet->assignments()) === \count($generated->crossword->entries())
                                && $publicationQuality->final >= $request->qualityThreshold) {
                                return BuildResult::success($publicationKey, $snapshot, $requestHash, $generated->crossword, $clues->clueSet, $generated->metadata, $publicationQuality, $versions, $warnings, $fallbacks);
                            }
                            $bestRejectedQuality = max($bestRejectedQuality ?? 0, $publicationQuality->final);
                            $lastFailure = new BuildFailure(
                                $clues->isValid() ? 'quality_threshold_rejected' : 'clue_set_invalid',
                                $clues->isValid() ? 'Complete candidate did not pass the publication-quality threshold.' : 'Assigned clue set failed validation.',
                                ['quality' => $publicationQuality->final, 'threshold' => $request->qualityThreshold, 'violations' => \count($clues->violations())],
                            );
                        } catch (ClueAssignmentFailed $exception) {
                            $lastFailure = new BuildFailure('no_valid_clues', $exception->getMessage());
                        } catch (\Throwable) {
                            $lastFailure = new BuildFailure('clue_assignment_failed', 'Clue assignment or publication-quality evaluation failed unexpectedly.');
                        }
                    } elseif (!$generated->succeeded()) {
                        $generationFailure = $generated->failure;
                        $lastFailure = $generationFailure === null
                            ? new BuildFailure('generation_failed', 'Generation failed without structured details.')
                            : new BuildFailure($generationFailure->code, $generationFailure->message, $generationFailure->context);
                    }
                }
            }
            ++$run;

            $transitioned = false;
            while ($stepIndex < \count($request->failurePolicy->steps())) {
                $step = $request->failurePolicy->steps()[$stepIndex++];
                $from = $this->state($strategy, $dimensions);
                if ($step->action === FallbackAction::Postpone || $step->action === FallbackAction::Fail) {
                    $fallbacks[] = new FallbackRecord(\count($fallbacks) + 1, $step->action, $from, null, $lastFailure->code);
                    $status = $step->action === FallbackAction::Postpone ? BuildStatus::Postponed : BuildStatus::Failed;

                    return BuildResult::failure($status, $publicationKey, $snapshot, $requestHash, $lastFailure, $warnings, $fallbacks, $lastGeneration, $versions);
                }
                $nextStrategy = $step->action === FallbackAction::Strategy ? $step->strategy : $strategy;
                $nextDimensions = $step->action === FallbackAction::Dimensions ? $step->dimensions : $dimensions;
                \assert($nextStrategy instanceof GenerationStrategy && $nextDimensions instanceof GridDimensions);
                $to = $this->state($nextStrategy, $nextDimensions);
                if ($step->action !== FallbackAction::Retry && isset($seenConfigurations[$to])) {
                    $fallbacks[] = new FallbackRecord(\count($fallbacks) + 1, $step->action, $from, null, 'fallback_cycle_prevented');
                    continue;
                }
                $strategy = $nextStrategy;
                $dimensions = $nextDimensions;
                $seenConfigurations[$to] = true;
                $fallbacks[] = new FallbackRecord(\count($fallbacks) + 1, $step->action, $from, $to, $lastFailure->code);
                $transitioned = true;
                break;
            }
            if (!$transitioned) {
                break;
            }
        }

        $context = ['runs' => $run, 'best_rejected_quality' => $bestRejectedQuality];
        $failure = new BuildFailure('global_work_budget_exhausted', 'The global builder work budget was exhausted before a publishable result was found.', $context);

        return BuildResult::failure(BuildStatus::Failed, $publicationKey, $snapshot, $requestHash, $failure, $warnings, $fallbacks, $lastGeneration, $versions);
    }

    /**
     * @param list<string> $warnings
     * @param list<FallbackRecord> $fallbacks
     */
    private function failure(
        BuildStatus $status,
        string $publicationKey,
        string $snapshot,
        string $requestHash,
        string $code,
        string $message,
        array $warnings,
        array $fallbacks,
        ?GenerationMetadata $generation = null,
        ?VersionSnapshot $versions = null,
    ): BuildResult {
        return BuildResult::failure($status, $publicationKey, $snapshot, $requestHash, new BuildFailure($code, $message), $warnings, $fallbacks, $generation, $versions);
    }

    private function versions(PackDescriptor $pack): VersionSnapshot
    {
        $answer = $pack->answerPack->metadata();
        $learning = $pack->learningPack?->manifest();

        return new VersionSnapshot(
            self::VERSION,
            \Crosseno\Generator\DeterministicGenerator::VERSION,
            $answer->packId,
            $answer->dataVersion,
            $pack->cluePackId,
            $pack->cluePackVersion,
            $learning?->packId,
            $learning?->dataVersion,
        );
    }

    private function runSeed(GenerationSeed $seed, int $run): GenerationSeed
    {
        return $run === 0 ? $seed : new GenerationSeed(substr(hash('sha256', $seed->unsignedHex . "\0" . $run), 0, 16));
    }

    private function state(GenerationStrategy $strategy, GridDimensions $dimensions): string
    {
        return $strategy->value . ':' . $dimensions->rows . 'x' . $dimensions->columns;
    }
}
