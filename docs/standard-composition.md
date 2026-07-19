# Standard composition

`StandardBuilderFactory::PROFILE_ID` is `crosseno-standard-builder-v1`. Its defaults are `DefaultGeneratorFactory` with the SHA-256 counter randomizer and deterministic scorer, a deterministic clue assigner with language, sense, difficulty, length, and leakage validators, `StandardQualityEvaluator`, `NullUsageHistory`, `SystemClock`, and the standard manifest compatibility validator.

Every default is an optional constructor dependency and can be replaced. The factory is language-neutral: leakage services and answer language come from `RuntimeLanguagePackInterface`. It creates a one-pack catalog via `PackDescriptor::fromRuntimePack()`, which validates manifest/artifact identity and consumes the runtime pack's exact ordinal map.

`NullUsageHistory` reports history as unavailable. Requests that need history must either inject a real adapter or explicitly choose the documented missing-history policy. Requests with a recent-use window of zero do not query history.

`StandardBuildRequestFactory::PROFILE_ID` is `crosseno-standard-build-request-v1`. Its experimental defaults are balanced strategy, medium difficulty, no recent-use lookup, a 600,000 quality threshold, bounded retry then fast-strategy fallback, a three-run deterministic work budget, minimum entry length three, connected/no-duplicate/adjacency-safe validation, and standard resource limits. Every value is replaceable in the constructor; the resulting object is still a normal immutable `BuildRequest`.

For synchronous examples, `StandardBuilderFactory::synchronousCancellation()` returns the existing generator `NeverCancelled` implementation. Long-running hosts should pass their own cancellation token to `CrosswordBuilder::build()`.
