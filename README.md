# crosseno/builder

Storage-neutral orchestration for producing complete, publication-ready Crosseno crosswords. Applications normally call this package rather than invoking the generator and clue assigner separately. CMS adapters remain responsible for persistence, transactions, idempotency storage, and scheduling.

## Installation

For the checked-in English runtime path, install `crosseno/builder`, `crosseno/clues`, and `crosseno/language-en`. The language package brings its SQLite catalog and solver-index runtime dependencies.

```bash
composer require crosseno/builder crosseno/clues crosseno/language-en
```

## Quick start

Load the runtime pack, adapt its storage-neutral clue catalog, and use the versioned standard composition:

```php
use Crosseno\Builder\StandardBuilderFactory;
use Crosseno\Builder\Policy\FailurePolicy;
use Crosseno\Builder\Policy\WorkBudget;
use Crosseno\Builder\Request\BuildDifficulty;
use Crosseno\Builder\Request\IdempotencyKey;
use Crosseno\Builder\Request\StandardBuildRequestFactory;
use Crosseno\Clues\Catalog\LexicalCatalogClueProvider;
use Crosseno\Core\Grid\GridDimensions;
use Crosseno\Core\ResourceLimits;
use Crosseno\Core\Validation\ValidationProfile;
use Crosseno\Generator\Seed\GenerationSeed;
use Crosseno\Generator\Strategy\GenerationStrategy;
use Crosseno\LanguageEn\EnglishLanguagePack;
use Crosseno\Lexicon\Language\LanguageCode;

$limits = ResourceLimits::standard();
$runtime = EnglishLanguagePack::load($limits);
$provider = new LexicalCatalogClueProvider(
    $runtime->catalog(),
    $runtime->metadata()->stableKeyAlgorithmVersion,
);
$builder = (new StandardBuilderFactory())->create(
    $runtime,
    $provider,
    new LanguageCode('en'),
);
$request = (new StandardBuildRequestFactory(
    strategy: GenerationStrategy::Fast,
    difficulty: BuildDifficulty::Easy,
    qualityThreshold: 0,
    failurePolicy: FailurePolicy::fail(),
    workBudget: new WorkBudget(1, 16, 100_000, 100_000),
    validationProfile: ValidationProfile::permissive(),
))->create(
    new LanguageCode('en'),
    new LanguageCode('en'),
    new GridDimensions(7, 7),
    GenerationSeed::fromInteger(12345),
);
$result = $builder->build(
    $request,
    new IdempotencyKey('example-12345'),
    StandardBuilderFactory::synchronousCancellation(),
);

if (!$result->succeeded()) {
    echo $result->failure?->code . ': ' . $result->failure?->message;
    var_dump($result->failure?->context, $result->warnings(), $result->fallbacks());
    return;
}

$grid = $result->crossword?->grid;
$entries = $result->crossword?->entries();
$clues = $result->clues?->assignments();
```

`StandardBuilderFactory::PROFILE_ID` identifies the composition defaults. Each default—generator factory, clue assigner, quality evaluator, usage history, clock, and compatibility validator—can be replaced in its constructor. `PackDescriptor::fromRuntimePack()` obtains the supported ordinal map and validates runtime identity without artifact parsing.

Run the executable integration example with readable output or explicitly labelled non-canonical debug JSON:

```bash
php examples/generate-english.php --rows=7 --columns=7 --strategy=fast --difficulty=easy --seed=12345
php examples/generate-english.php --rows=7 --columns=7 --strategy=fast --difficulty=easy --seed=12345 --debug-json
```

The bundled 1,000-answer English development pack makes this an integration and first-scale proof, not a production vocabulary benchmark. The example deliberately uses a bounded fast request, a permissive structural profile, and a zero publication threshold locally; production defaults are not weakened. The language pack's documented benchmark matrix covers `5×5`, `7×7`, and `9×9` requests, but does not claim a general production success rate.

## Advanced manual composition

Low-level dependency injection remains available for CMS authors and custom workflows:

```php
use Crosseno\Builder\CrosswordBuilder;
use Crosseno\Builder\Generation\EligibilityBuilder;
use Crosseno\Builder\Pack\PackResolver;

$builder = new CrosswordBuilder(
    new PackResolver($packCatalog),
    new EligibilityBuilder(),
    $generatorFactory,
    $clueAssigner,
    $qualityEvaluator,
    $usageHistory,
    $clock,
);
```

`BuildResult` is an immutable, persistence-ready object graph, but it is not a canonical interchange format. Stable export remains the responsibility of the future `crosseno/formats` boundary.

See [getting started](docs/getting-started.md), [standard composition](docs/standard-composition.md), [manual composition](docs/manual-composition.md), [host integration](docs/host-integration.md), [supported package matrix](docs/supported-package-matrix.md), and [troubleshooting](docs/troubleshooting.md).

```bash
composer install
composer check
```
