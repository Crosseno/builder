<?php

declare(strict_types=1);

namespace Crosseno\Builder\History;

use Crosseno\Builder\Contract\UsageHistoryInterface;

final readonly class NullUsageHistory implements UsageHistoryInterface
{
    public function lookup(UsageHistoryQuery $query): UsageHistorySnapshot
    {
        return UsageHistorySnapshot::unavailable();
    }
}
