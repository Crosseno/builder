# Host integration

Builder is a storage-neutral application service. A CMS, worker, or CLI host supplies installed runtime packs, clue providers, optional learning packs, usage history, idempotency keys, cancellation, and policy overrides. The host also owns persistence, transactions, scheduling, authorization, CSRF/nonces, audit logging, leases, and publication.

## Idempotency and concurrency

Derive a durable `IdempotencyKey` from the host job/publication identity and persist the resulting publication key under a unique constraint. Queue workers should acquire an expiring lease, use bounded retries, and atomically publish only a successful `BuildResult`. Builder produces deterministic keys and complete results but does not lock or store them.

## History and cancellation

Implement `UsageHistoryInterface` as a read-only bounded lookup. Do not translate an unavailable store into an empty snapshot; return unavailable and let `RecentUsePolicy` decide. Interactive or leased jobs should implement `CancellationTokenInterface`; `synchronousCancellation()` is only for short processes without cancellation infrastructure.

## Failures and diagnostics

Persist structured failure codes, bounded scalar context, warnings, fallback records, and version metadata. Do not persist source payloads, clue catalog rows, filesystem paths, credentials, or exception traces in user-visible diagnostics. Failed and postponed results never contain partial publishable crossword/clue content.

## Rendering and formats

Clue and source text is untrusted plain data. Escape it for HTML, attributes, JSON, terminal, or other output contexts. `BuildResult` is persistence-ready but not a canonical exchange format; use the future `crosseno/formats` layer for stable serialization.
