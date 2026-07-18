<?php

declare(strict_types=1);

namespace Crosseno\Builder\Policy;

enum FallbackAction: string
{
    case Retry = 'retry';
    case Strategy = 'strategy';
    case Dimensions = 'dimensions';
    case Postpone = 'postpone';
    case Fail = 'fail';
}
