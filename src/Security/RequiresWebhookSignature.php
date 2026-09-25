<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Marks a controller whose request body must carry a valid HMAC signature.
 * Checked before argument resolution, so unsigned payloads are never deserialized.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class RequiresWebhookSignature
{
    public const HEADER = 'X-Signature';
}
