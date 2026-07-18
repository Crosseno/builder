<?php

declare(strict_types=1);

namespace Crosseno\Builder\Contract;

use Crosseno\Builder\Pack\PackDescriptor;

interface PackCatalogInterface
{
    /** @return list<PackDescriptor> Stable for an unchanged installed pack set. */
    public function packs(): array;
}
