<?php

declare(strict_types=1);

namespace Crosseno\Builder\History;

enum MissingHistoryPolicy: string
{
    case Fail = 'fail';
    case ProceedWithWarning = 'proceed_with_warning';
}
