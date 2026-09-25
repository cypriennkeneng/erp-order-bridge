<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Verifies "X-Signature: sha256=<hex>" headers, where <hex> is the
 * HMAC-SHA256 of the raw request body with the shared webhook secret.
 */
final class WebhookSignatureVerifier
{
    private const PREFIX = 'sha256=';

    public function __construct(
        #[\SensitiveParameter]
        private readonly string $webhookSecret,
    ) {
        if ('' === $webhookSecret) {
            throw new \InvalidArgumentException('The webhook secret must not be empty.');
        }
    }

    public function sign(string $body): string
    {
        return self::PREFIX.hash_hmac('sha256', $body, $this->webhookSecret);
    }

    public function isValid(string $body, ?string $signatureHeader): bool
    {
        if (null === $signatureHeader || !str_starts_with($signatureHeader, self::PREFIX)) {
            return false;
        }

        // Constant-time comparison, so the signature cannot be guessed byte by byte.
        return hash_equals($this->sign($body), $signatureHeader);
    }
}
