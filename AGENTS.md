# Repository guidance

- Keep orchestration storage-neutral and free of CMS persistence or transaction ownership.
- Resolve and validate the complete pack tuple before invoking generation.
- Keep all nested retries and fallbacks under one deterministic work budget.
- Never treat unavailable history as an empty history without an explicit request policy.
- Return only complete, valid clue sets that pass the publication-quality gate.
- Run `composer check` before handoff.
