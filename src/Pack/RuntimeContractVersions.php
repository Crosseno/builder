<?php

declare(strict_types=1);

namespace Crosseno\Builder\Pack;

use Crosseno\Builder\Exception\InvalidBuildRequest;

final readonly class RuntimeContractVersions
{
    public function __construct(
        public string $core = '0.1.0',
        public string $lexicon = '0.1.0',
        public string $clues = '0.1.0',
        public string $learning = '0.1.0',
    ) {
        foreach (['core' => $core, 'lexicon' => $lexicon, 'clues' => $clues, 'learning' => $learning] as $contract => $version) {
            if (preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/D', $version) !== 1) {
                throw new InvalidBuildRequest(\sprintf('Runtime %s contract version must be semantic.', $contract));
            }
        }
    }
}
