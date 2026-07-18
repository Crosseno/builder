<?php

declare(strict_types=1);

namespace Crosseno\Builder\Contract;

use Crosseno\Generator\Contract\LayoutGeneratorInterface;
use Crosseno\Lexicon\Contract\SolverIndexInterface;
use Crosseno\Lexicon\Manifest\LanguagePackManifest;

interface GeneratorFactoryInterface
{
    public function create(SolverIndexInterface $index, LanguagePackManifest $manifest): LayoutGeneratorInterface;
}
