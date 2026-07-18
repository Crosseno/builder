<?php

declare(strict_types=1);

namespace Crosseno\Builder\Request;

enum ClueMode: string
{
    case Standard = 'standard';
    case Learning = 'learning';
}
