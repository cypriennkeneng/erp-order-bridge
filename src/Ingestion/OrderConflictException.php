<?php

declare(strict_types=1);

namespace App\Ingestion;

use App\Entity\Order;

/**
 * Same (source, orderNumber) as a stored order, but different content.
 * Silently overwriting would corrupt what the ERP already has.
 */
final class OrderConflictException extends \RuntimeException
{
    public function __construct(
        public readonly Order $existing,
    ) {
        parent::__construct(\sprintf(
            'Order "%s" from "%s" was already received with different content.',
            $existing->getExternalId(),
            $existing->getSource(),
        ));
    }
}
