<?php

declare(strict_types=1);

namespace Crosseno\Builder\Generation;

use Crosseno\Clues\Contract\AnswerClueProviderInterface;
use Crosseno\Clues\Contract\ClueProviderInterface;
use Crosseno\Clues\Model\Clue;
use Crosseno\Clues\Query\ClueQuery;
use Crosseno\Core\Answer\AnswerKey;

final readonly class FilteringClueProvider implements AnswerClueProviderInterface
{
    /** @var array<string, true>|null */
    private ?array $allowedIds;

    /** @var array<string, true> */
    private array $excludedIds;

    /**
     * @param null|list<string> $allowedIds
     * @param list<string> $excludedIds
     */
    public function __construct(private ClueProviderInterface $inner, ?array $allowedIds, array $excludedIds)
    {
        $this->allowedIds = $allowedIds === null ? null : array_fill_keys($allowedIds, true);
        $this->excludedIds = array_fill_keys($excludedIds, true);
    }

    /** @return iterable<Clue> */
    public function provide(ClueQuery $query): iterable
    {
        $expanded = new ClueQuery($query->senseKey, $query->language, $query->languageMatching, $query->types(), ClueQuery::MAXIMUM_RESULTS);
        foreach ($this->filtered($this->inner->provide($expanded), $query->maximumResults) as $clue) {
            yield $clue;
        }
    }

    /** @return iterable<Clue> */
    public function provideForAnswer(AnswerKey $answerKey, ClueQuery $query): iterable
    {
        if (!$this->inner instanceof AnswerClueProviderInterface) {
            return;
        }

        $expanded = new ClueQuery(null, $query->language, $query->languageMatching, $query->types(), ClueQuery::MAXIMUM_RESULTS);
        foreach ($this->filtered($this->inner->provideForAnswer($answerKey, $expanded), $query->maximumResults) as $clue) {
            yield $clue;
        }
    }

    /**
     * @param iterable<mixed> $clues
     * @return \Generator<int, Clue, void, void>
     */
    private function filtered(iterable $clues, int $maximumResults): iterable
    {
        $count = 0;
        foreach ($clues as $clue) {
            if (!$clue instanceof Clue || isset($this->excludedIds[$clue->id->value])
                || ($this->allowedIds !== null && !isset($this->allowedIds[$clue->id->value]))) {
                continue;
            }
            yield $clue;
            if (++$count >= $maximumResults) {
                return;
            }
        }
    }
}
