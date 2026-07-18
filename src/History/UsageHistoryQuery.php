<?php

declare(strict_types=1);

namespace Crosseno\Builder\History;

use Crosseno\Lexicon\Language\LanguageCode;

final readonly class UsageHistoryQuery
{
    public function __construct(
        public LanguageCode $answerLanguage,
        public LanguageCode $clueLanguage,
        public int $maximumBuilds,
    ) {}
}
