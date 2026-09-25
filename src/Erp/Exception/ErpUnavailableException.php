<?php

declare(strict_types=1);

namespace App\Erp\Exception;

/** Timeout, network error, 5xx or 429 from the ERP. Retryable. */
final class ErpUnavailableException extends \RuntimeException
{
}
