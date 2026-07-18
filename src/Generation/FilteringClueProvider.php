<?php

declare(strict_types=1);

namespace Crosseno\Builder\Generation;

use Crosseno\Clues\Contract\ClueProviderInterface;
use Crosseno\Clues\Model\Clue;
use Crosseno\Clues\Query\ClueQuery;

final readonly class FilteringClueProvider implements ClueProviderInterface
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

    public function provide(ClueQuery $query): iterable
    {
        $expanded = new ClueQuery($query->senseKey, $query->language, $query->languageMatching, $query->types(), ClueQuery::MAXIMUM_RESULTS);
        $count = 0;
        foreach ($this->inner->provide($expanded) as $clue) {
            if (!$clue instanceof Clue || isset($this->excludedIds[$clue->id->value])
                || ($this->allowedIds !== null && !isset($this->allowedIds[$clue->id->value]))) {
                continue;
            }
            yield $clue;
            if (++$count >= $query->maximumResults) {
                return;
            }
        }
    }
}
