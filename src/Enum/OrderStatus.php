<?php

declare(strict_types=1);

namespace App\Enum;

enum OrderStatus: string
{
    /** Stored locally, export to the ERP pending or being retried. */
    case Received = 'received';

    /** Accepted by the ERP; erpReference is set. */
    case Exported = 'exported';

    /** Rejected by the ERP or retries exhausted; needs a human. */
    case Failed = 'failed';
}
