<?php

declare(strict_types=1);

namespace Crosseno\Builder\Pack;

use Crosseno\Builder\Exception\PackResolutionFailed;
use Crosseno\Learning\Manifest\LearningPackManifest;
use Crosseno\Lexicon\Manifest\LanguagePackManifest;

final readonly class ManifestCompatibilityValidator
{
    public function __construct(private RuntimeContractVersions $runtime = new RuntimeContractVersions()) {}

    public function assertLanguagePack(LanguagePackManifest $manifest): void
    {
        $this->assertAxis('core', $this->runtime->core, $manifest->minimumCoreVersion, 'answer');
        $this->assertAxis('lexicon', $this->runtime->lexicon, $manifest->minimumLexiconVersion, 'answer');
    }

    public function assertLearningPack(LearningPackManifest $manifest): void
    {
        $this->assertAxis('core', $this->runtime->core, $manifest->minimumCoreVersion, 'learning');
        $this->assertAxis('lexicon', $this->runtime->lexicon, $manifest->minimumLexiconVersion, 'learning');
        $this->assertAxis('clues', $this->runtime->clues, $manifest->minimumCluesVersion, 'learning');
        $this->assertAxis('learning', $this->runtime->learning, $manifest->minimumLearningVersion, 'learning');
    }

    private function assertAxis(string $axis, string $installed, string $minimum, string $packType): void
    {
        if (version_compare($installed, $minimum, '<')) {
            throw new PackResolutionFailed(\sprintf(
                'The %s pack requires %s contract %s or newer; runtime version is %s.',
                $packType,
                $axis,
                $minimum,
                $installed,
            ));
        }
    }
}
