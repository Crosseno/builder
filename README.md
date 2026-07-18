# crosseno/builder

Storage-neutral orchestration for producing publication-ready Crosseno crosswords.

The API resolves compatible answer, clue, and optional directional learning packs; applies explicit history and fallback policies; runs bounded generation; assigns and validates clues; evaluates publication quality; and returns an immutable persistence-ready result.

CMS and CLI adapters provide read-only usage history and own persistence, idempotency storage, and transactions.

Construct the builder from host-owned adapters, then submit an immutable request:

```php
use Crosseno\Builder\CrosswordBuilder;
use Crosseno\Builder\Generation\EligibilityBuilder;
use Crosseno\Builder\Pack\PackResolver;

$builder = new CrosswordBuilder(
    new PackResolver($packCatalog),
    new EligibilityBuilder(),
    $generatorFactory,
    $clueAssigner,
    $qualityEvaluator,
    $usageHistory,
);

$result = $builder->build($request, $idempotencyKey, $cancellationToken);
if ($result->publishable()) {
    $publisher->store($result);
}
```

The host supplies the catalog, generation/clue policies, request, idempotency
key, cancellation token, and publisher. `BuildResult` is persistence-ready as
an immutable object graph, but it is intentionally not a canonical serialized
publication format. The host maps it to storage; canonical cross-format export
belongs to the future `crosseno/formats` boundary.

```bash
composer install
composer check
```
