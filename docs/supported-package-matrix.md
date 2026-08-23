# Supported package matrix

The standard Builder runtime remains language-neutral. The English full-stack proof is validated against the following package line:

| Package | Compatible line | Role |
|---|---:|---|
| `crosseno/builder` | next `0.1.x` release | Orchestration and standard composition |
| `crosseno/core` | `^0.1` | Crossword domain and validation |
| `crosseno/lexicon` | `^0.1` | Runtime language-pack contracts |
| `crosseno/generator` | next `0.1.x` release | Deterministic generation |
| `crosseno/clues` | next `0.1.x` release | Catalog adapter, assignment, and validation |
| `crosseno/learning` | next `0.1.x` release | Optional learning-pack contracts |
| `crosseno/language-en` | `^0.2` | English runtime artifacts used by the integration proof |
| `crosseno/lexicon-index` | `^0.1` | Solver-index runtime used by `language-en` |
| `crosseno/lexicon-sqlite` | `^0.1` | Catalog runtime used by `language-en` |

All packages require PHP `^8.5`. Builder's runtime constraints intentionally exclude English and its storage implementations; they are development dependencies used by the real-stack proof.

## Release gate

Before publishing the next Builder release:

1. Tag the release candidates in dependency order: `lexicon`; `lexicon-sqlite`; `compiler`; `lexicon-index`; `generator` and `clues`; `learning`; `language-en`; then `builder`. `core` already has a compatible `0.1.0` release.
2. Publish all compatible tagged packages to Packagist.
3. In an empty directory with no workspace path repositories, run:

   ```bash
   composer require \
     crosseno/builder:^0.1 \
     crosseno/clues:^0.1 \
     crosseno/language-en:^0.2
   composer show crosseno/builder
   composer show crosseno/clues
   composer show crosseno/language-en
   php vendor/crosseno/builder/examples/generate-english.php \
     --rows=7 --columns=7 --seed=12345
   ```

4. Run the manual `Release installation` GitHub Actions workflow with the published Builder and English-pack constraints. It performs the same clean resolution and executable proof on PHP 8.5.

The release is not installation-verified until those commands resolve exclusively from tagged distributions. CI injects sibling path repositories through Composer's global root configuration for source-level checks; published package manifests contain no path repositories, and workspace resolution is not evidence of a published installation.
