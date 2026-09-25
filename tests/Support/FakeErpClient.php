<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Erp\ErpClientInterface;

/**
 * Records calls and answers with a canned reference or exception.
 */
final class FakeErpClient implements ErpClientInterface
{
    /** @var list<array{payload: array<string, mixed>, idempotencyKey: string}> */
    public array $calls = [];

    private string $reference = 'SO-TEST-1';
    private ?\Throwable $exception = null;

    public function willReturn(string $reference): void
    {
        $this->reference = $reference;
        $this->exception = null;
    }

    public function willThrow(\Throwable $exception): void
    {
        $this->exception = $exception;
    }

    public function createSalesOrder(array $payload, string $idempotencyKey): string
    {
        $this->calls[] = ['payload' => $payload, 'idempotencyKey' => $idempotencyKey];

        if (null !== $this->exception) {
            throw $this->exception;
        }

        return $this->reference;
    }
}
