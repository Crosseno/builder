<?php

declare(strict_types=1);

namespace Crosseno\Builder\Result;

enum BuildStatus: string
{
    case Success = 'success';
    case Postponed = 'postponed';
    case Failed = 'failed';
}
