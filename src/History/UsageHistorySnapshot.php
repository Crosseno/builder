<?php

declare(strict_types=1);

namespace Crosseno\Builder\History;

use Crosseno\Builder\Exception\InvalidBuildRequest;
use Crosseno\Clues\Model\ClueId;
use Crosseno\Lexicon\Identity\StableAnswerKey;

final readonly class UsageHistorySnapshot
{
    /** @var list<StableAnswerKey> */
    private array $answerKeys;

    /** @var list<ClueId> */
    private array $clueIds;

    /**
     * @param list<StableAnswerKey> $answerKeys
     * @param list<ClueId> $clueIds
     */
    private function __construct(public bool $available, array $answerKeys, array $clueIds)
    {
        if (!array_is_list($answerKeys) || !array_is_list($clueIds) || (!$available && ($answerKeys !== [] || $clueIds !== []))) {
            throw new InvalidBuildRequest('Usage-history snapshot is inconsistent.');
        }
        $answers = [];
        foreach ($answerKeys as $key) {
            if (!$key instanceof StableAnswerKey) {
                throw new InvalidBuildRequest('Usage history contains an invalid answer key.');
            }
            $answers[$key->coreKey->value] = $key;
        }
        $clues = [];
        foreach ($clueIds as $id) {
            if (!$id instanceof ClueId) {
                throw new InvalidBuildRequest('Usage history contains an invalid clue ID.');
            }
            $clues[$id->value] = $id;
        }
        ksort($answers, SORT_STRING);
        ksort($clues, SORT_STRING);
        $this->answerKeys = array_values($answers);
        $this->clueIds = array_values($clues);
    }

    /**
     * @param list<StableAnswerKey> $answerKeys
     * @param list<ClueId> $clueIds
     */
    public static function available(array $answerKeys = [], array $clueIds = []): self
    {
        return new self(true, $answerKeys, $clueIds);
    }

    public static function unavailable(): self
    {
        return new self(false, [], []);
    }

    /** @return list<StableAnswerKey> */
    public function answerKeys(): array
    {
        return $this->answerKeys;
    }

    /** @return list<ClueId> */
    public function clueIds(): array
    {
        return $this->clueIds;
    }
}
