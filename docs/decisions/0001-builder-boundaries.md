# ADR 0001: Storage-neutral bounded orchestration

The builder receives immutable pack descriptors and a read-only usage-history contract. It returns data but never stores it. Retry and fallback steps are an ordered request value and share a single deterministic run/node/backtrack budget. Pack compatibility is checked before search, and incomplete or sub-threshold candidates are never successful results.
