# Getting started

Install `crosseno/builder`, `crosseno/clues`, and one runtime language package such as `crosseno/language-en`. `EnglishLanguagePack::load(ResourceLimits::standard())` verifies and loads `manifest.json`, `catalog.sqlite`, and `solver.idx` as one compatible runtime tuple.

Create `LexicalCatalogClueProvider` from `catalog()` and the pack stable-key version, then pass both to `StandardBuilderFactory::create()`. `StandardBuildRequestFactory` supplies the versioned balanced request defaults; construct it with replacements when the host needs different strategy, difficulty, history, quality, failure, budget, entry-length, validation, or resource-limit policy. Call `build()` with an `IdempotencyKey` and `StandardBuilderFactory::synchronousCancellation()` for a simple synchronous process.

The 1,000-record English development pack has a deterministic benchmark matrix for `5×5`, `7×7`, and `9×9` requests, but it is not a production corpus and does not establish a general success rate. Its [executable example](../examples/generate-english.php) deliberately uses bounded fast-strategy integration settings without changing the reusable standard profile.

On failure, inspect `BuildResult::$failure`, `warnings()`, and `fallbacks()`. On success, access the grid and placed entries through `BuildResult::$crossword` and the matching assignments through `BuildResult::$clues`. A successful result always contains generation metadata, publication scores, versions, a structurally accepted crossword, and a complete clue set.

For bilingual builds, inject a `LanguageServiceProviderInterface` with leakage services for the clue language into `StandardBuilderFactory`, or provide a custom clue assigner. The standard factory rejects bilingual composition early when clue-language services are unavailable. Successful version metadata records both the standard composition profile and the concrete clue-assignment algorithm.
