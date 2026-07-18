<?php

declare(strict_types=1);

namespace Crosseno\Builder\Policy;

use Crosseno\Builder\Exception\InvalidBuildRequest;
use Crosseno\Core\Grid\GridDimensions;
use Crosseno\Generator\Strategy\GenerationStrategy;

final readonly class FallbackStep implements \JsonSerializable
{
    private function __construct(
        public FallbackAction $action,
        public ?GenerationStrategy $strategy = null,
        public ?GridDimensions $dimensions = null,
    ) {
        if (($action === FallbackAction::Strategy) !== ($strategy instanceof GenerationStrategy)
            || ($action === FallbackAction::Dimensions) !== ($dimensions instanceof GridDimensions)) {
            throw new InvalidBuildRequest('Fallback action payload is inconsistent.');
        }
    }

    public static function retry(): self
    {
        return new self(FallbackAction::Retry);
    }

    public static function strategy(GenerationStrategy $strategy): self
    {
        return new self(FallbackAction::Strategy, $strategy);
    }

    public static function dimensions(GridDimensions $dimensions): self
    {
        return new self(FallbackAction::Dimensions, dimensions: $dimensions);
    }

    public static function postpone(): self
    {
        return new self(FallbackAction::Postpone);
    }

    public static function fail(): self
    {
        return new self(FallbackAction::Fail);
    }

    /** @return array{action: string, strategy: ?string, dimensions: ?array{rows: int, columns: int}} */
    public function jsonSerialize(): array
    {
        return [
            'action' => $this->action->value,
            'strategy' => $this->strategy?->value,
            'dimensions' => $this->dimensions === null ? null : ['rows' => $this->dimensions->rows, 'columns' => $this->dimensions->columns],
        ];
    }
}
