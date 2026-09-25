<?php

declare(strict_types=1);

namespace App\Ingestion;

use App\Entity\Order;

final class IngestionResult
{
    public function __construct(
        public readonly Order $order,
        public readonly bool $created,
    ) {
    }
}
