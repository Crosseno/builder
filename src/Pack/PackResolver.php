<?php

declare(strict_types=1);

namespace Crosseno\Builder\Pack;

use Crosseno\Builder\Contract\PackCatalogInterface;
use Crosseno\Builder\Exception\PackResolutionFailed;
use Crosseno\Builder\Request\BuildRequest;
use Crosseno\Builder\Request\ClueMode;

final readonly class PackResolver
{
    public function __construct(private PackCatalogInterface $catalog) {}

    public function resolve(BuildRequest $request): PackDescriptor
    {
        $matches = [];
        foreach ($this->catalog->packs() as $pack) {
            if ($pack->answerPack->metadata()->answerLanguage->value !== $request->answerLanguage->value
                || $pack->clueLanguage->value !== $request->clueLanguage->value) {
                continue;
            }
            $needsLearning = $request->clueMode === ClueMode::Learning
                || $request->answerLanguage->value !== $request->clueLanguage->value;
            if ($needsLearning && $pack->learningPack === null) {
                continue;
            }
            if ($pack->learningPack !== null) {
                $learning = $pack->learningPack->manifest();
                $answerManifest = $pack->answerPack->manifest();
                if ($learning->languages->answerLanguage->value !== $request->answerLanguage->value
                    || $learning->languages->clueLanguage->value !== $request->clueLanguage->value
                    || !$learning->answerPack->matches($answerManifest)) {
                    continue;
                }
            }
            $matches[] = $pack;
        }
        if ($matches === []) {
            throw new PackResolutionFailed('No installed pack matches the complete language and artifact compatibility tuple.');
        }
        usort($matches, static fn(PackDescriptor $left, PackDescriptor $right): int => strcmp($left->identity(), $right->identity()));

        return $matches[0];
    }
}
