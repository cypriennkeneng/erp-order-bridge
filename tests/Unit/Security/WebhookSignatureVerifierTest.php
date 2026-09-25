<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\WebhookSignatureVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebhookSignatureVerifierTest extends TestCase
{
    public function testAcceptsTheSignatureItProduces(): void
    {
        $verifier = new WebhookSignatureVerifier('secret');
        $body = '{"orderNumber":"10042"}';

        self::assertTrue($verifier->isValid($body, $verifier->sign($body)));
    }

    public function testMatchesAnIndependentlyComputedHmac(): void
    {
        $verifier = new WebhookSignatureVerifier('secret');

        self::assertSame('sha256='.hash_hmac('sha256', 'payload', 'secret'), $verifier->sign('payload'));
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function invalidHeaders(): iterable
    {
        yield 'missing header' => [null];
        yield 'empty header' => [''];
        yield 'no prefix' => [hash_hmac('sha256', 'payload', 'secret')];
        yield 'wrong secret' => ['sha256='.hash_hmac('sha256', 'payload', 'other-secret')];
        yield 'tampered body' => ['sha256='.hash_hmac('sha256', 'payload!', 'secret')];
    }

    #[DataProvider('invalidHeaders')]
    public function testRejectsInvalidSignatures(?string $header): void
    {
        self::assertFalse((new WebhookSignatureVerifier('secret'))->isValid('payload', $header));
    }

    public function testRefusesAnEmptySecret(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new WebhookSignatureVerifier('');
    }
}
