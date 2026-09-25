<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Carries only the order id: the handler always works on the current
 * database state, never on a stale copy serialized into the queue.
 */
final class ExportOrderToErp
{
    public function __construct(
        public readonly string $orderId,
    ) {
    }
}
