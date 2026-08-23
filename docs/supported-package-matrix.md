# Supported package matrix

The standard Builder runtime remains language-neutral. The English full-stack proof is validated against the following published package line:

| Package | Verified release | Compatible line | Role |
|---|---:|---:|---|
| `crosseno/builder` | `0.1.1` | `^0.1` | Orchestration and standard composition |
| `crosseno/core` | `0.1.0` | `^0.1` | Crossword domain and validation |
| `crosseno/lexicon` | `0.1.2` | `^0.1` | Runtime language-pack contracts |
| `crosseno/generator` | `0.1.0` | `^0.1` | Deterministic generation |
| `crosseno/clues` | `0.1.1` | `^0.1` | Catalog adapter, assignment, and validation |
| `crosseno/learning` | `0.1.0` | `^0.1` | Optional learning-pack contracts |
| `crosseno/language-en` | `0.2.2` | `^0.2` | English runtime artifacts used by the integration proof |
| `crosseno/lexicon-index` | `0.1.1` | `^0.1` | Solver-index runtime used by `language-en` |
| `crosseno/lexicon-sqlite` | `0.1.1` | `^0.1` | Catalog runtime used by `language-en` |
| `crosseno/compiler` | `0.1.1` | `^0.1` | Offline language-pack build tooling |

All packages require PHP `^8.5`. Builder's runtime constraints intentionally exclude English and its storage implementations; they are development dependencies used by the real-stack proof.

## Release verification

The Step 10A release gate completed on 2026-08-23:

1. Compatible releases were tagged in dependency order and published to Packagist.
2. Published manifests contain no path repositories.
3. An empty project resolved the tagged distributions and ran:

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

4. The manual [Release installation](https://github.com/Crosseno/builder/actions/workflows/release-install.yml) workflow passed with the published Builder and English-pack constraints on PHP 8.5.

Source-level CI injects sibling path repositories through Composer's global root configuration. The separate release workflow intentionally uses only tagged Packagist distributions so workspace resolution cannot mask a packaging defect.
