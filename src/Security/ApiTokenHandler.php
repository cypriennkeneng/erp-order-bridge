<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Single static bearer token for the read API. Enough for a machine-to-machine
 * back office; swap for OIDC/JWT validation when real users are involved.
 */
final class ApiTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        #[\SensitiveParameter]
        private readonly string $apiToken,
    ) {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        if ('' === $this->apiToken || !hash_equals($this->apiToken, $accessToken)) {
            throw new BadCredentialsException('Invalid API token.');
        }

        return new UserBadge('api-client');
    }
}
