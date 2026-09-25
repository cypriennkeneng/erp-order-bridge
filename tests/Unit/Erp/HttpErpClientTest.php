<?php

declare(strict_types=1);

namespace App\Tests\Unit\Erp;

use App\Erp\Exception\ErpRejectedException;
use App\Erp\Exception\ErpUnavailableException;
use App\Erp\HttpErpClient;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpErpClientTest extends TestCase
{
    public function testReturnsTheDocumentNumberAndSendsTheIdempotencyKey(): void
    {
        $response = new JsonMockResponse(['documentNumber' => 'SO-2026-00001'], ['http_code' => 201]);
        $client = new HttpErpClient(new MockHttpClient($response, 'https://erp.test/api/'));

        $reference = $client->createSalesOrder(['externalNumber' => '10042'], 'key-123');

        self::assertSame('SO-2026-00001', $reference);
        self::assertSame('POST', $response->getRequestMethod());
        self::assertSame('https://erp.test/api/sales-orders', $response->getRequestUrl());
        $headers = $response->getRequestOptions()['headers'];
        self::assertIsArray($headers);
        self::assertContains('Idempotency-Key: key-123', $headers);
        self::assertSame('{"externalNumber":"10042"}', $response->getRequestOptions()['body']);
    }

    public function testTreatsAnAlreadyProcessedIdempotencyKeyAsSuccess(): void
    {
        $client = new HttpErpClient(new MockHttpClient(
            new JsonMockResponse(['documentNumber' => 'SO-2026-00001'], ['http_code' => 409]),
            'https://erp.test/api/',
        ));

        self::assertSame('SO-2026-00001', $client->createSalesOrder([], 'key-123'));
    }

    #[TestWith([500])]
    #[TestWith([502])]
    #[TestWith([503])]
    #[TestWith([429])]
    public function testServerErrorsAndThrottlingAreRetryable(int $status): void
    {
        $client = new HttpErpClient(new MockHttpClient(
            new JsonMockResponse(['message' => 'busy'], ['http_code' => $status]),
            'https://erp.test/api/',
        ));

        $this->expectException(ErpUnavailableException::class);

        $client->createSalesOrder([], 'key-123');
    }

    public function testNetworkErrorsAreRetryable(): void
    {
        $client = new HttpErpClient(new MockHttpClient(
            new MockResponse('', ['error' => 'Connection timed out']),
            'https://erp.test/api/',
        ));

        $this->expectException(ErpUnavailableException::class);

        $client->createSalesOrder([], 'key-123');
    }

    public function testClientErrorsAreRejectionsWithTheErpMessage(): void
    {
        $client = new HttpErpClient(new MockHttpClient(
            new JsonMockResponse(['message' => 'Article "X" does not exist.'], ['http_code' => 422]),
            'https://erp.test/api/',
        ));

        $this->expectException(ErpRejectedException::class);
        $this->expectExceptionMessage('HTTP 422): Article "X" does not exist.');

        $client->createSalesOrder([], 'key-123');
    }

    public function testNonJsonErrorBodiesDoNotHideTheStatus(): void
    {
        $client = new HttpErpClient(new MockHttpClient(
            new MockResponse('<html>Bad Request</html>', ['http_code' => 400]),
            'https://erp.test/api/',
        ));

        $this->expectException(ErpRejectedException::class);
        $this->expectExceptionMessage('HTTP 400): no details');

        $client->createSalesOrder([], 'key-123');
    }

    public function testSuccessWithoutDocumentNumberIsNotTakenAsSuccess(): void
    {
        $client = new HttpErpClient(new MockHttpClient(
            new JsonMockResponse(['status' => 'ok'], ['http_code' => 201]),
            'https://erp.test/api/',
        ));

        $this->expectException(ErpUnavailableException::class);

        $client->createSalesOrder([], 'key-123');
    }
}
