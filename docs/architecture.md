# Architecture

`CrosswordBuilder` is an application service only. Pack catalogs expose immutable runtime dependencies, the resolver validates language direction and artifact compatibility, usage history remains caller-owned and read-only, the generator owns search, and the clue package owns clue selection and validation. The builder owns deterministic orchestration, global work accounting, fallback decisions, publication quality, and persistence-ready metadata.

No persistence operation is performed by this package.
