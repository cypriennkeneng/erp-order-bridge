<?php

declare(strict_types=1);

namespace App\Erp\Exception;

/** 4xx from the ERP: the data is wrong (unknown SKU, invalid customer...). Not retryable. */
final class ErpRejectedException extends \RuntimeException
{
}
