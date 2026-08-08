# Standard composition

`StandardBuilderFactory::PROFILE_ID` is `crosseno-standard-builder-v1`. Its defaults are `DefaultGeneratorFactory` with the SHA-256 counter randomizer and deterministic scorer, a deterministic clue assigner with language, sense, difficulty, length, and leakage validators, `StandardQualityEvaluator`, `NullUsageHistory`, `SystemClock`, and the standard manifest compatibility validator.

Every default is an optional constructor dependency and can be replaced. For same-language builds, leakage services come from `RuntimeLanguagePackInterface`. Bilingual builds must inject a `LanguageServiceProviderInterface` containing services for the requested clue language, or replace the clue assigner; composition fails explicitly when neither is available. It creates a one-pack catalog via `PackDescriptor::fromRuntimePack()`, which validates manifest/artifact identity and verifies the actual ordinal map against the manifest digest and stable-key algorithm.

The factory profile and the successful clue-assignment algorithm are retained in `VersionSnapshot`. Answers with multiple senses remain unresolved during generation; bounded answer-aware clue lookup selects a concrete sense after placement, and the successful crossword retains that selected sense.

`NullUsageHistory` reports history as unavailable. Requests that need history must either inject a real adapter or explicitly choose the documented missing-history policy. Requests with a recent-use window of zero do not query history.

`StandardBuildRequestFactory::PROFILE_ID` is `crosseno-standard-build-request-v1`. Its experimental defaults are balanced strategy, medium difficulty, no recent-use lookup, a 600,000 quality threshold, bounded retry then fast-strategy fallback, a three-run deterministic work budget, minimum entry length three, connected/no-duplicate/adjacency-safe validation, and standard resource limits. Every value is replaceable in the constructor; the resulting object is still a normal immutable `BuildRequest`.

For synchronous examples, `StandardBuilderFactory::synchronousCancellation()` returns the existing generator `NeverCancelled` implementation. Long-running hosts should pass their own cancellation token to `CrosswordBuilder::build()`.
