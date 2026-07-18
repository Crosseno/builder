<?php

declare(strict_types=1);

namespace Crosseno\Builder\Pack;

use Crosseno\Builder\Contract\PackCatalogInterface;
use Crosseno\Builder\Exception\InvalidBuildRequest;

final readonly class InMemoryPackCatalog implements PackCatalogInterface
{
    /** @var list<PackDescriptor> */
    private array $packs;

    /** @param list<PackDescriptor> $packs */
    public function __construct(array $packs)
    {
        if (!array_is_list($packs)) {
            throw new InvalidBuildRequest('Pack catalog must be a list.');
        }
        $result = [];
        foreach ($packs as $pack) {
            if (!$pack instanceof PackDescriptor || isset($result[$pack->identity()])) {
                throw new InvalidBuildRequest('Pack catalog contains an invalid or duplicate tuple.');
            }
            $result[$pack->identity()] = $pack;
        }
        ksort($result, SORT_STRING);
        $this->packs = array_values($result);
    }

    public function packs(): array
    {
        return $this->packs;
    }
}
