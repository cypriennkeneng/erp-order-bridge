<?php

declare(strict_types=1);

namespace App\Erp;

use App\Erp\Exception\ErpRejectedException;
use App\Erp\Exception\ErpUnavailableException;

interface ErpClientInterface
{
    /**
     * Creates a sales order and returns the ERP document number.
     *
     * @param array<string, mixed> $payload as built by ErpOrderMapper
     *
     * @throws ErpRejectedException    the ERP refused the data; retrying will not help
     * @throws ErpUnavailableException the ERP could not be reached or failed; retry later
     */
    public function createSalesOrder(array $payload, string $idempotencyKey): string;
}
