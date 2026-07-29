<?php

declare(strict_types=1);

namespace Crosseno\Builder\Tests\Integration;

use Crosseno\Builder\History\MissingHistoryPolicy;
use Crosseno\Builder\History\RecentUsePolicy;
use Crosseno\Builder\Policy\FailurePolicy;
use Crosseno\Builder\Policy\WorkBudget;
use Crosseno\Builder\Request\BuildDifficulty;
use Crosseno\Builder\Request\BuildRequest;
use Crosseno\Builder\Request\IdempotencyKey;
use Crosseno\Builder\Request\StandardBuildRequestFactory;
use Crosseno\Builder\Result\BuildResult;
use Crosseno\Builder\StandardBuilderFactory;
use Crosseno\Clues\Catalog\LexicalCatalogClueProvider;
use Crosseno\Core\Grid\GridDimensions;
use Crosseno\Core\ResourceLimits;
use Crosseno\Core\Validation\CompositeValidator;
use Crosseno\Core\Validation\ValidationProfile;
use Crosseno\Generator\DeterministicGenerator;
use Crosseno\Generator\Seed\GenerationSeed;
use Crosseno\Generator\Strategy\GenerationStrategy;
use Crosseno\LanguageEn\EnglishLanguagePack;
use Crosseno\Lexicon\Language\LanguageCode;
use PHPUnit\Framework\TestCase;

final class EnglishFullStackTest extends TestCase
{
    public function testRealEnglishStackProducesDeterministicGoldenResultWithCompleteMetadata(): void
    {
        [$builder, $request] = self::stack(self::request(new GridDimensions(7, 7), 3, 7));
        $key = new IdempotencyKey('english-full-stack-7x7-12345');
        $first = $builder->build($request, $key, StandardBuilderFactory::synchronousCancellation());
        $second = $builder->build($request, $key, StandardBuilderFactory::synchronousCancellation());

        self::assertTrue($first->succeeded(), $first->failure?->message ?? 'Build failed.');
        self::assertTrue($second->succeeded(), $second->failure?->message ?? 'Repeated build failed.');
        self::assertNotNull($first->crossword);
        self::assertNotNull($first->clues);
        self::assertNotNull($first->generation);
        self::assertNotNull($first->quality);
        self::assertNotNull($first->versions);
        self::assertNotEmpty($first->crossword->entries());
        self::assertCount(\count($first->crossword->entries()), $first->clues->assignments());
        self::assertSame([], CompositeValidator::standard()->validate($first->crossword, $request->validationProfile));
        self::assertSame(DeterministicGenerator::VERSION, $first->generation->generatorVersion);
        self::assertTrue($first->generation->strictlyReproducible);
        self::assertNotNull($first->generation->scores);
        self::assertGreaterThan(0, $first->generation->attempts);
        self::assertGreaterThan(0, $first->generation->exploredNodes);
        self::assertSame('0000000000003039', $first->generation->seed->unsignedHex);
        self::assertSame('crosseno.language-en.dev', $first->versions->answerPackId);
        self::assertSame('2026.07.3', $first->versions->answerPackVersion);
        self::assertSame($first->publicationKey, $second->publicationKey);
        self::assertSame(self::golden($first), self::golden($second));
        self::assertSame(
            ['hard', 'earth', 'dream', 'shop'],
            array_map(static fn($entry): string => $entry->answer->displayText, $first->crossword->entries()),
        );
        self::assertSame([
            '3:1:horizontal:4',
            '1:3:vertical:5',
            '1:1:horizontal:5',
            '5:2:horizontal:4',
        ], array_map(static fn($entry): string => $entry->placement->signature(), $first->crossword->entries()));
    }

    public function testInsufficientDevelopmentDataReturnsSpecificDiagnostics(): void
    {
        [$builder, $request] = self::stack(self::request(new GridDimensions(20, 20), 20, 20));
        $result = $builder->build(
            $request,
            new IdempotencyKey('english-insufficient-data'),
            StandardBuilderFactory::synchronousCancellation(),
        );

        self::assertFalse($result->succeeded());
        self::assertSame('candidate_pool_too_small', $result->failure?->code);
        self::assertSame('Candidate pool cannot satisfy the strategy minimum entry count.', $result->failure?->message);
        self::assertNotNull($result->generation);
        self::assertSame(DeterministicGenerator::VERSION, $result->generation->generatorVersion);
        self::assertNull($result->crossword);
        self::assertNull($result->clues);
    }

    /** @return array{\Crosseno\Builder\CrosswordBuilder, BuildRequest} */
    private static function stack(BuildRequest $request): array
    {
        $runtime = EnglishLanguagePack::load($request->resourceLimits);
        $provider = new LexicalCatalogClueProvider(
            $runtime->catalog(),
            $runtime->metadata()->stableKeyAlgorithmVersion,
        );

        return [
            (new StandardBuilderFactory())->create($runtime, $provider, new LanguageCode('en')),
            $request,
        ];
    }

    private static function request(GridDimensions $dimensions, int $minimumEntryLength, int $maximumEntryLength): BuildRequest
    {
        $language = new LanguageCode('en');

        return (new StandardBuildRequestFactory(
            strategy: GenerationStrategy::Fast,
            difficulty: BuildDifficulty::Easy,
            recentUse: new RecentUsePolicy(0, MissingHistoryPolicy::ProceedWithWarning),
            qualityThreshold: 0,
            failurePolicy: FailurePolicy::fail(),
            workBudget: new WorkBudget(1, 16, 100_000, 100_000),
            minimumEntryLength: $minimumEntryLength,
            validationProfile: ValidationProfile::permissive(),
            resourceLimits: ResourceLimits::standard(),
        ))->create(
            $language,
            $language,
            $dimensions,
            GenerationSeed::fromInteger(12_345),
            maximumEntryLength: $maximumEntryLength,
        );
    }

    private static function golden(BuildResult $result): string
    {
        return hash('sha256', json_encode([
            'answers' => $result->answerKeys(),
            'senses' => $result->senseKeys(),
            'placements' => array_map(static fn($entry): string => $entry->placement->signature(), $result->crossword?->entries() ?? []),
            'clues' => $result->clues,
            'scores' => $result->quality,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
