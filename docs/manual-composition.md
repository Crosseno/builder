# Manual composition

Use the public `CrosswordBuilder` constructor when a host needs multiple pack tuples or custom generation, clue, quality, history, clock, or compatibility behavior. Construct `PackDescriptor` directly only when the host owns an independently versioned clue or learning pack. For a runtime language pack, prefer `PackDescriptor::fromRuntimePack()` so ordinal identity is not reconstructed manually.

The host owns persistence, transaction boundaries, scheduling, idempotency storage, and publication. Builder owns compatible pack resolution, one bounded retry/fallback budget, generation, complete clue assignment, and the publication-quality gate.
