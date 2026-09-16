<?php

namespace Webkul\Bagisto\Helpers\Exporters\Product;

use Webkul\DataTransfer\Helpers\Sources\Export\ProductCursor;

class CollectionCursor extends ProductCursor
{
    public function __construct(protected array $rows)
    {
        parent::__construct([], null);
    }

    protected function fetchNextBatch(): array
    {
        if ($this->offset > 0) {
            return [];
        }

        $this->offset++;

        return $this->rows;
    }

    public function count(): int
    {
        return count($this->rows);
    }
}
