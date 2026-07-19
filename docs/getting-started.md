# Getting started

Install `crosseno/builder`, `crosseno/clues`, and one runtime language package such as `crosseno/language-en`. `EnglishLanguagePack::load(ResourceLimits::standard())` verifies and loads `manifest.json`, `catalog.sqlite`, and `solver.idx` as one compatible runtime tuple.

Create `LexicalCatalogClueProvider` from `catalog()` and the pack stable-key version, then pass both to `StandardBuilderFactory::create()`. `StandardBuildRequestFactory` supplies the versioned balanced request defaults; construct it with replacements when the host needs different strategy, difficulty, history, quality, failure, budget, entry-length, validation, or resource-limit policy. Call `build()` with an `IdempotencyKey` and `StandardBuilderFactory::synchronousCancellation()` for a simple synchronous process.

The 25-record English development pack cannot satisfy the ordinary balanced defaults reliably. Its [executable example](../examples/generate-english.php) deliberately replaces them with small-grid integration settings without changing the reusable standard profile.

On failure, inspect `BuildResult::$failure`, `warnings()`, and `fallbacks()`. On success, access the grid and placed entries through `BuildResult::$crossword` and the matching assignments through `BuildResult::$clues`. A successful result always contains generation metadata, publication scores, versions, a structurally accepted crossword, and a complete clue set.
