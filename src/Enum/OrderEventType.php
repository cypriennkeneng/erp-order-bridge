<?php

declare(strict_types=1);

namespace App\Enum;

enum OrderEventType: string
{
    case Received = 'received';
    case ExportAttempted = 'export_attempted';
    case Exported = 'exported';
    case ExportDeferred = 'export_deferred';
    case ExportFailed = 'export_failed';
    case Requeued = 'requeued';
}
