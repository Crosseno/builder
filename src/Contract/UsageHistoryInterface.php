<?php

declare(strict_types=1);

namespace Crosseno\Builder\Contract;

use Crosseno\Builder\History\UsageHistoryQuery;
use Crosseno\Builder\History\UsageHistorySnapshot;

interface UsageHistoryInterface
{
    public function lookup(UsageHistoryQuery $query): UsageHistorySnapshot;
}
