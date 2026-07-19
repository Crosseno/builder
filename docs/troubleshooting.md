# Troubleshooting

- `pack_resolution_failed`: verify answer/clue languages, contract minimums, artifact hashes, and optional learning-pack identity.
- `empty_eligibility_mask`: check requested difficulty, clue coverage language, themes, history exclusions, and learning coverage.
- `no_valid_clues` or `clue_assignment_failed`: verify every placed answer has a stable sense key and compatible clue record with supported type, language, difficulty, and text.
- `quality_threshold_rejected`: inspect component scores and use thresholds appropriate to the chosen pack; do not change global production policy for a fixture.
- `global_work_budget_exhausted`: use a supported request, strategy, or explicit larger work budget.

Failures never contain partial publishable crossword/clue content. Generation metadata may be present when generation ran before the failure.
