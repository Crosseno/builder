# English end-to-end example

`examples/generate-english.php` executes the complete checked-in path:

```text
language-en manifest/catalog/index -> lexical clue adapter -> deterministic generator
-> deterministic clue assignment and validators -> publication quality -> BuildResult
```

Use `--rows`, `--columns`, `--strategy`, `--difficulty`, and `--seed` to control the request. The default is a deliberately small `5×5` fast/easy development-pack request; `7×7` with seed `12345` is also covered by the current golden integration test. Output includes the grid, Across/Down clues, strategy/profile version, seed, component scores, and diagnostics.

`--debug-json` is explicitly non-canonical diagnostic output. Do not persist or exchange it as a stable format.
