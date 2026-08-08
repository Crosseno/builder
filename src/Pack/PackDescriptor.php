<?php

declare(strict_types=1);

namespace Crosseno\Builder\Pack;

use Crosseno\Builder\Exception\InvalidBuildRequest;
use Crosseno\Clues\Contract\ClueProviderInterface;
use Crosseno\Learning\Contract\LearningPackInterface;
use Crosseno\Lexicon\Contract\LanguagePackInterface;
use Crosseno\Lexicon\Contract\RuntimeLanguagePackInterface;
use Crosseno\Lexicon\Identity\StableAnswerKey;
use Crosseno\Lexicon\Language\LanguageCode;

final readonly class PackDescriptor
{
    /** @var list<StableAnswerKey> */
    private array $answersByOrdinal;

    /** @param list<StableAnswerKey> $answersByOrdinal Exact answer-pack ordinal order. */
    public function __construct(
        public LanguagePackInterface $answerPack,
        public LanguageCode $clueLanguage,
        public string $cluePackId,
        public string $cluePackVersion,
        public ClueProviderInterface $clueProvider,
        array $answersByOrdinal,
        public ?LearningPackInterface $learningPack = null,
    ) {
        $manifest = $answerPack->manifest();
        $metadata = $answerPack->metadata();
        if ($manifest->metadata->packId !== $metadata->packId
            || $manifest->metadata->answerLanguage->value !== $metadata->answerLanguage->value
            || $manifest->metadata->dataVersion !== $metadata->dataVersion
            || $manifest->metadata->normalizationProfileId !== $metadata->normalizationProfileId
            || $manifest->metadata->tokenizationProfileId !== $metadata->tokenizationProfileId
            || $manifest->metadata->stableKeyAlgorithmVersion->major !== $metadata->stableKeyAlgorithmVersion->major) {
            throw new InvalidBuildRequest('Answer-pack metadata and manifest metadata differ.');
        }
        foreach (['clue pack ID' => $cluePackId, 'clue pack version' => $cluePackVersion] as $label => $value) {
            if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._+-]{0,127}$/D', $value) !== 1) {
                throw new InvalidBuildRequest(ucfirst($label) . ' must be a portable identifier.');
            }
        }
        if (!array_is_list($answersByOrdinal) || \count($answersByOrdinal) !== $manifest->recordCount) {
            throw new InvalidBuildRequest('Answer ordinal map must match the answer-pack record count.');
        }
        $seen = [];
        foreach ($answersByOrdinal as $key) {
            if (!$key instanceof StableAnswerKey
                || $key->algorithmVersion->major !== $manifest->metadata->stableKeyAlgorithmVersion->major
                || isset($seen[$key->coreKey->value])) {
                throw new InvalidBuildRequest('Answer ordinal map must contain unique stable answer keys using the manifest algorithm version.');
            }
            $seen[$key->coreKey->value] = true;
        }
        $digest = hash('sha256', implode("\n", array_keys($seen)));
        if (!hash_equals($manifest->stableKeyDigest, $digest)) {
            throw new InvalidBuildRequest('Answer ordinal map does not match the answer-pack stable-key digest.');
        }
        $this->answersByOrdinal = $answersByOrdinal;
    }

    public static function fromRuntimePack(
        RuntimeLanguagePackInterface $runtimePack,
        LanguageCode $clueLanguage,
        ClueProviderInterface $clueProvider,
        ?string $cluePackId = null,
        ?string $cluePackVersion = null,
        ?LearningPackInterface $learningPack = null,
    ): self {
        $identity = $runtimePack->runtimeIdentity();
        $manifest = $runtimePack->manifest();
        if ($identity->packId !== $manifest->metadata->packId
            || $identity->dataVersion !== $manifest->metadata->dataVersion
            || $identity->ordinalSpaceId !== $manifest->ordinalSpaceId
            || !hash_equals($identity->stableKeyDigest, $manifest->stableKeyDigest)) {
            throw new InvalidBuildRequest('Runtime-pack artifact and ordinal-space identity disagrees with its manifest.');
        }

        return new self(
            $runtimePack,
            $clueLanguage,
            $cluePackId ?? $identity->packId,
            $cluePackVersion ?? $identity->dataVersion,
            $clueProvider,
            $runtimePack->answerKeysByOrdinal(),
            $learningPack,
        );
    }

    /** @return list<StableAnswerKey> */
    public function answersByOrdinal(): array
    {
        return $this->answersByOrdinal;
    }

    public function identity(): string
    {
        $answer = $this->answerPack->metadata();
        $learning = $this->learningPack?->manifest();

        $learningId = $learning === null ? '-' : $learning->packId;
        $learningVersion = $learning === null ? '-' : $learning->dataVersion;

        return implode('|', [
            $answer->packId,
            $answer->dataVersion,
            $this->cluePackId,
            $this->cluePackVersion,
            $learningId,
            $learningVersion,
        ]);
    }
}
