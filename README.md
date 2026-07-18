# crosseno/builder

Storage-neutral orchestration for producing publication-ready Crosseno crosswords.

The API resolves compatible answer, clue, and optional directional learning packs; applies explicit history and fallback policies; runs bounded generation; assigns and validates clues; evaluates publication quality; and returns an immutable persistence-ready result.

CMS and CLI adapters provide read-only usage history and own persistence, idempotency storage, and transactions.

```bash
composer install
composer check
```
