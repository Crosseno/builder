# Architecture

`CrosswordBuilder` is an application service only. Pack catalogs expose immutable runtime dependencies, the resolver validates language direction and artifact compatibility, usage history remains caller-owned and read-only, the generator owns search, and the clue package owns clue selection and validation. The builder owns deterministic orchestration, global work accounting, fallback decisions, publication quality, and persistence-ready metadata.

No persistence operation is performed by this package.

## Composition layers

`CrosswordBuilder` and its constructor are the low-level dependency-injected service. `StandardBuilderFactory` is the versioned convenience layer: it consumes `RuntimeLanguagePackInterface` plus a clue provider and assembles replaceable standard generator, clue, quality, history, clock, and compatibility components.

The runtime pack is responsible for verifying and exposing language services, the rich storage-neutral catalog, solver index, exact ordinal map, clue coverage, and artifact/ordinal identity. Builder never parses SQLite or binary artifacts and does not inspect concrete language-package internals.

Hosts may use `PackDescriptor::fromRuntimePack()` in custom multi-pack catalogs. Manual ordinal construction remains supported for independently assembled legacy/adaptor tuples, but runtime-pack construction is preferred because it validates identity and count invariants.

CMS hosts own persistence, transactions, scheduling, and idempotency storage. Debug JSON from the executable example is non-canonical; stable interchange belongs to the future `crosseno/formats` package.
