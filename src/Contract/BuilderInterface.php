<?php

declare(strict_types=1);

namespace Crosseno\Builder\Contract;

use Crosseno\Builder\Request\BuildRequest;
use Crosseno\Builder\Request\IdempotencyKey;
use Crosseno\Builder\Result\BuildResult;
use Crosseno\Generator\Budget\CancellationTokenInterface;

interface BuilderInterface
{
    public function build(BuildRequest $request, IdempotencyKey $idempotencyKey, CancellationTokenInterface $cancellation): BuildResult;
}
