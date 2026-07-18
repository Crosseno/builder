<?php

declare(strict_types=1);

namespace Crosseno\Builder\Quality;

use Crosseno\Builder\Contract\QualityEvaluatorInterface;
use Crosseno\Clues\Assignment\ClueAssignmentResult;
use Crosseno\Generator\Result\GenerationMetadata;

final readonly class StandardQualityEvaluator implements QualityEvaluatorInterface
{
    public function evaluate(GenerationMetadata $generation, ClueAssignmentResult $clues, int $expectedClues): PublicationQuality
    {
        $structural = $generation->scores?->structural->millionths ?? 0;
        $lexical = $generation->scores?->lexical->millionths ?? 0;
        $assignments = $clues->clueSet->assignments();
        $clueTotal = array_sum(array_map(static fn($assignment): int => max(0, min(10_000, $assignment->score)), $assignments));
        $clueScore = $assignments === [] ? 0 : intdiv($clueTotal * 100, \count($assignments));
        $completeness = $expectedClues === 0 ? 0 : intdiv(\count($assignments) * 1_000_000, $expectedClues);
        if (!$clues->isValid() || \count($assignments) !== $expectedClues) {
            $completeness = min(999_999, $completeness);
        }
        $final = intdiv(
            ($structural * 300_000) + ($lexical * 200_000) + ($clueScore * 300_000) + ($completeness * 200_000),
            1_000_000,
        );

        return new PublicationQuality($structural, $lexical, $clueScore, $completeness, $final);
    }
}
