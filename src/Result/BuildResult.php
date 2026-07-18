<?php

declare(strict_types=1);

namespace Crosseno\Builder\Result;

use Crosseno\Builder\Exception\InvalidBuildRequest;
use Crosseno\Builder\Quality\PublicationQuality;
use Crosseno\Clues\Assignment\ClueSet;
use Crosseno\Core\Crossword\Crossword;
use Crosseno\Generator\Result\GenerationMetadata;

final readonly class BuildResult
{
    /** @var list<string> */
    private array $answerKeys;

    /** @var list<?string> */
    private array $senseKeys;

    /** @var list<string> */
    private array $warnings;

    /** @var list<FallbackRecord> */
    private array $fallbacks;

    /**
     * @param list<string> $warnings
     * @param list<FallbackRecord> $fallbacks
     */
    private function __construct(
        public BuildStatus $status,
        public string $publicationKey,
        public string $requestSnapshot,
        public string $requestHash,
        public ?Crossword $crossword,
        public ?ClueSet $clues,
        public ?GenerationMetadata $generation,
        public ?PublicationQuality $quality,
        public ?VersionSnapshot $versions,
        array $warnings,
        array $fallbacks,
        public ?BuildFailure $failure,
    ) {
        $success = $status === BuildStatus::Success;
        if ($success !== ($crossword instanceof Crossword && $clues instanceof ClueSet
            && $generation instanceof GenerationMetadata && $quality instanceof PublicationQuality
            && $versions instanceof VersionSnapshot && $failure === null)) {
            throw new InvalidBuildRequest('Build result state is inconsistent.');
        }
        if (!$success && ($crossword !== null || $clues !== null || $quality !== null || $failure === null)) {
            throw new InvalidBuildRequest('Failed or postponed result cannot contain publishable content.');
        }
        $this->answerKeys = $crossword === null ? [] : array_map(
            static fn($entry): string => $entry->answer->key->value,
            $crossword->entries(),
        );
        $this->senseKeys = $crossword === null ? [] : array_map(
            static fn($entry): ?string => $entry->senseKey?->value,
            $crossword->entries(),
        );
        $this->warnings = array_values($warnings);
        $this->fallbacks = array_values($fallbacks);
    }

    /**
     * @param list<string> $warnings
     * @param list<FallbackRecord> $fallbacks
     */
    public static function success(
        string $publicationKey,
        string $requestSnapshot,
        string $requestHash,
        Crossword $crossword,
        ClueSet $clues,
        GenerationMetadata $generation,
        PublicationQuality $quality,
        VersionSnapshot $versions,
        array $warnings,
        array $fallbacks,
    ): self {
        return new self(BuildStatus::Success, $publicationKey, $requestSnapshot, $requestHash, $crossword, $clues, $generation, $quality, $versions, $warnings, $fallbacks, null);
    }

    /**
     * @param list<string> $warnings
     * @param list<FallbackRecord> $fallbacks
     */
    public static function failure(
        BuildStatus $status,
        string $publicationKey,
        string $requestSnapshot,
        string $requestHash,
        BuildFailure $failure,
        array $warnings,
        array $fallbacks,
        ?GenerationMetadata $generation = null,
        ?VersionSnapshot $versions = null,
    ): self {
        if ($status === BuildStatus::Success) {
            throw new InvalidBuildRequest('Failure factory cannot create a successful result.');
        }

        return new self($status, $publicationKey, $requestSnapshot, $requestHash, null, null, $generation, null, $versions, $warnings, $fallbacks, $failure);
    }

    public function succeeded(): bool
    {
        return $this->status === BuildStatus::Success;
    }

    /** @return list<string> */
    public function answerKeys(): array
    {
        return $this->answerKeys;
    }

    /** @return list<?string> */
    public function senseKeys(): array
    {
        return $this->senseKeys;
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /** @return list<FallbackRecord> */
    public function fallbacks(): array
    {
        return $this->fallbacks;
    }
}
