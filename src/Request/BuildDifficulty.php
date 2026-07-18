<?php

declare(strict_types=1);

namespace Crosseno\Builder\Request;

use Crosseno\Lexicon\Candidate\BoundType;
use Crosseno\Lexicon\Candidate\Difficulty;
use Crosseno\Lexicon\Candidate\DifficultyRange;

enum BuildDifficulty: string
{
    case Easy = 'easy';
    case Medium = 'medium';
    case Hard = 'hard';

    public function answerRange(): DifficultyRange
    {
        return match ($this) {
            self::Easy => new DifficultyRange(new Difficulty(0), BoundType::Inclusive, new Difficulty(39), BoundType::Inclusive),
            self::Medium => new DifficultyRange(new Difficulty(30), BoundType::Inclusive, new Difficulty(74), BoundType::Inclusive),
            self::Hard => new DifficultyRange(new Difficulty(65), BoundType::Inclusive, new Difficulty(100), BoundType::Inclusive),
        };
    }

    public function clueTarget(): int
    {
        return match ($this) {
            self::Easy => 25,
            self::Medium => 55,
            self::Hard => 85,
        };
    }
}
