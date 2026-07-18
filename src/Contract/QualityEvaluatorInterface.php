<?php

declare(strict_types=1);

namespace Crosseno\Builder\Contract;

use Crosseno\Builder\Quality\PublicationQuality;
use Crosseno\Clues\Assignment\ClueAssignmentResult;
use Crosseno\Generator\Result\GenerationMetadata;

interface QualityEvaluatorInterface
{
    public function evaluate(GenerationMetadata $generation, ClueAssignmentResult $clues, int $expectedClues): PublicationQuality;
}
