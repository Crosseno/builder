#!/usr/bin/env php
<?php

declare(strict_types=1);

use Crosseno\Builder\History\MissingHistoryPolicy;
use Crosseno\Builder\History\RecentUsePolicy;
use Crosseno\Builder\Policy\FailurePolicy;
use Crosseno\Builder\Policy\WorkBudget;
use Crosseno\Builder\Request\BuildDifficulty;
use Crosseno\Builder\Request\IdempotencyKey;
use Crosseno\Builder\Request\StandardBuildRequestFactory;
use Crosseno\Builder\StandardBuilderFactory;
use Crosseno\Clues\Catalog\LexicalCatalogClueProvider;
use Crosseno\Core\Grid\CellStateType;
use Crosseno\Core\Grid\Direction;
use Crosseno\Core\Grid\GridDimensions;
use Crosseno\Core\ResourceLimits;
use Crosseno\Core\Validation\ValidationProfile;
use Crosseno\Generator\DeterministicGenerator;
use Crosseno\Generator\Seed\GenerationSeed;
use Crosseno\Generator\Strategy\GenerationStrategy;
use Crosseno\LanguageEn\EnglishLanguagePack;
use Crosseno\Lexicon\Language\LanguageCode;

require \dirname(__DIR__) . '/vendor/autoload.php';

$options = getopt('', ['rows::', 'columns::', 'strategy::', 'difficulty::', 'seed::', 'debug-json']);
$rows = positiveInteger($options['rows'] ?? '5', 'rows');
$columns = positiveInteger($options['columns'] ?? '5', 'columns');
if ($rows < 3 || $columns < 3) {
    throw new InvalidArgumentException('--rows and --columns must be at least 3.');
}
$seedValue = nonNegativeInteger($options['seed'] ?? '12345', 'seed');
$strategyValue = $options['strategy'] ?? GenerationStrategy::Fast->value;
$difficultyValue = $options['difficulty'] ?? BuildDifficulty::Easy->value;
if (!\is_string($strategyValue) || ($strategy = GenerationStrategy::tryFrom($strategyValue)) === null) {
    throw new InvalidArgumentException('--strategy must be fast, balanced, high_quality, dense, or theme_focused.');
}
if (!\is_string($difficultyValue) || ($difficulty = BuildDifficulty::tryFrom($difficultyValue)) === null) {
    throw new InvalidArgumentException('--difficulty must be easy, medium, or hard.');
}
$debug = isset($options['debug-json']);
$limits = ResourceLimits::standard();
$language = new LanguageCode('en');

$runtime = EnglishLanguagePack::load($limits);
$clueProvider = new LexicalCatalogClueProvider(
    $runtime->catalog(),
    $runtime->metadata()->stableKeyAlgorithmVersion,
);
$builder = (new StandardBuilderFactory())->create($runtime, $clueProvider, $language);

// The 25-answer development pack needs deliberately small, permissive overrides.
$request = (new StandardBuildRequestFactory(
    strategy: $strategy,
    difficulty: $difficulty,
    recentUse: new RecentUsePolicy(0, MissingHistoryPolicy::ProceedWithWarning),
    qualityThreshold: 0,
    failurePolicy: FailurePolicy::fail(),
    workBudget: new WorkBudget(1, 16, 100_000, 100_000),
    minimumEntryLength: 3,
    validationProfile: ValidationProfile::permissive(),
    resourceLimits: $limits,
))->create(
    $language,
    $language,
    new GridDimensions($rows, $columns),
    GenerationSeed::fromInteger($seedValue),
    maximumEntryLength: max(3, min(max($rows, $columns), 12)),
);
$idempotencyKey = new IdempotencyKey('english-example-' . $rows . 'x' . $columns . '-' . $seedValue);
$result = $builder->build($request, $idempotencyKey, StandardBuilderFactory::synchronousCancellation());

if (!$result->succeeded()) {
    fwrite(STDERR, "Build failed: {$result->failure?->code}\n{$result->failure?->message}\n");
    if ($result->failure?->context !== []) {
        fwrite(STDERR, 'Diagnostics: ' . json_encode($result->failure->context, JSON_THROW_ON_ERROR) . "\n");
    }
    exit(1);
}

$repeat = $builder->build($request, $idempotencyKey, StandardBuilderFactory::synchronousCancellation());
if (!$repeat->succeeded()
    || $result->answerKeys() !== $repeat->answerKeys()
    || placementSignatures($result) !== placementSignatures($repeat)
    || json_encode($result->clues, JSON_THROW_ON_ERROR) !== json_encode($repeat->clues, JSON_THROW_ON_ERROR)) {
    throw new RuntimeException('The known request did not produce deterministic crossword and clue output.');
}
$entryCount = \count($result->crossword?->entries() ?? []);
if ($entryCount === 0 || \count($result->clues?->assignments() ?? []) !== $entryCount
    || $result->generation?->generatorVersion !== DeterministicGenerator::VERSION
    || !$result->generation->strictlyReproducible || $result->generation->scores === null
    || $result->versions === null || $result->quality === null) {
    throw new RuntimeException('The real-stack result is incomplete or did not use the deterministic generator.');
}

$crossword = $result->crossword;
$generation = $result->generation;
$quality = $result->quality;
assert($crossword !== null && $result->clues !== null && $generation !== null && $quality !== null);

echo "Crosseno English development-pack example\n\n";
foreach ($crossword->grid->cells() as $offset => $cell) {
    echo $cell->type === CellStateType::Filled ? $cell->symbol?->value : '·';
    echo (($offset + 1) % $columns) === 0 ? "\n" : ' ';
}

$numbers = [];
$starts = [];
foreach ($crossword->entries() as $entry) {
    $starts[$entry->placement->start->key()] = $entry->placement->start;
}
usort($starts, static fn($left, $right): int => $left->row <=> $right->row ?: $left->column <=> $right->column);
foreach (array_values($starts) as $index => $start) {
    $numbers[$start->key()] = $index + 1;
}
$assignments = [];
foreach ($result->clues->assignments() as $assignment) {
    $assignments[$assignment->entryIndex] = $assignment;
}
foreach ([[Direction::Horizontal, 'Across'], [Direction::Vertical, 'Down']] as [$direction, $heading]) {
    echo "\n{$heading}\n";
    $lines = [];
    foreach ($crossword->entries() as $index => $entry) {
        if ($entry->placement->direction !== $direction) {
            continue;
        }
        $assignment = $assignments[$index];
        $number = $numbers[$entry->placement->start->key()];
        $lines[$number] = \sprintf("%d. %s (%d)\n", $number, $assignment->clue->text, $entry->answer->length());
    }
    ksort($lines, SORT_NUMERIC);
    foreach ($lines as $line) {
        echo $line;
    }
}

echo "\nStrategy: {$generation->strategyProfile->strategy->value} {$generation->strategyProfile->version}\n";
echo "Seed: {$generation->seed->unsignedHex}\n";
echo 'Generation scores: ' . json_encode([
    'structural' => $generation->scores?->structural->millionths,
    'lexical' => $generation->scores?->lexical->millionths,
    'final' => $generation->scores?->final->millionths,
], JSON_THROW_ON_ERROR) . "\n";
echo 'Publication scores: ' . json_encode($quality, JSON_THROW_ON_ERROR) . "\n";
echo \sprintf(
    "Diagnostics: %d entries; %d attempts; %d nodes; %d backtracks; %d ms\n",
    \count($crossword->entries()),
    $generation->attempts,
    $generation->exploredNodes,
    $generation->backtracks,
    $generation->durationMilliseconds,
);

if ($debug) {
    echo "\nNON-CANONICAL DEBUG JSON\n";
    echo json_encode([
        'schema' => 'crosseno-debug-build-result-NON-CANONICAL',
        'status' => $result->status->value,
        'publication_key' => $result->publicationKey,
        'answer_keys' => $result->answerKeys(),
        'clues' => $result->clues,
        'quality' => $quality,
        'warnings' => $result->warnings(),
        'fallbacks' => $result->fallbacks(),
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
}

function positiveInteger(mixed $value, string $name): int
{
    $integer = nonNegativeInteger($value, $name);
    if ($integer === 0) {
        throw new InvalidArgumentException("--{$name} must be positive.");
    }

    return $integer;
}

function nonNegativeInteger(mixed $value, string $name): int
{
    if (!\is_string($value) || preg_match('/^(0|[1-9][0-9]*)$/D', $value) !== 1) {
        throw new InvalidArgumentException("--{$name} must be a non-negative integer.");
    }

    return (int) $value;
}

/** @return list<string> */
function placementSignatures(\Crosseno\Builder\Result\BuildResult $result): array
{
    return array_map(
        static fn($entry): string => $entry->placement->signature(),
        $result->crossword?->entries() ?? [],
    );
}
