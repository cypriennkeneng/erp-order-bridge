<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Message\ExportOrderToErp;
use App\Security\WebhookSignatureVerifier;
use App\Tests\Support\RecreatesDatabaseSchema;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class OrderWebhookTest extends WebTestCase
{
    use RecreatesDatabaseSchema;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        self::recreateSchema(self::entityManager());
    }

    public function testAcceptsASignedOrderStoresItAndQueuesTheExport(): void
    {
        $this->postSigned(self::payload());

        self::assertResponseStatusCodeSame(202);
        $body = $this->json();
        self::assertSame('10042', $body['orderNumber']);
        self::assertSame('received', $body['status']);
        self::assertSame('65.48', $body['totalGross']);

        $order = self::entityManager()->getRepository(Order::class)->findOneBy(['externalId' => '10042']);
        self::assertNotNull($order);
        self::assertSame(OrderStatus::Received, $order->getStatus());

        $sent = $this->asyncTransport()->getSent();
        self::assertCount(1, $sent);
        $message = $sent[0]->getMessage();
        self::assertInstanceOf(ExportOrderToErp::class, $message);
        self::assertSame($order->getId()->toRfc4122(), $message->orderId);
    }

    public function testRejectsAMissingSignature(): void
    {
        $this->client->request('POST', '/api/webhooks/orders', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(self::payload(), \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(401);
        self::assertCount(0, $this->asyncTransport()->getSent());
    }

    public function testRejectsASignatureForADifferentBody(): void
    {
        $signedBody = json_encode(self::payload(), \JSON_THROW_ON_ERROR);
        $tamperedBody = json_encode(self::payload(totalGross: 1, lines: [['sku' => 'X', 'name' => 'X', 'quantity' => 1, 'unitPrice' => 1]]), \JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/webhooks/orders', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE' => $this->verifier()->sign($signedBody),
        ], content: $tamperedBody);

        self::assertResponseStatusCodeSame(401);
    }

    public function testRejectsInvalidPayloadsWithTheViolations(): void
    {
        $this->postSigned(self::payload(totalGross: 999));

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('does not match the sum of the lines', (string) $this->client->getResponse()->getContent());
        self::assertCount(0, $this->asyncTransport()->getSent());
    }

    public function testAcknowledgesARepeatedDeliveryWithoutSideEffects(): void
    {
        $this->postSigned(self::payload());
        $firstId = $this->json()['id'];
        self::assertCount(1, $this->asyncTransport()->getSent());

        // The in-memory transport is reset with the kernel between requests,
        // so what we see below is exactly what the second request dispatched.
        $this->postSigned(self::payload());

        self::assertResponseStatusCodeSame(200);
        self::assertSame($firstId, $this->json()['id']);
        self::assertCount(1, self::entityManager()->getRepository(Order::class)->findAll());
        self::assertCount(0, $this->asyncTransport()->getSent(), 'the export must not be queued twice');
    }

    public function testReportsAConflictWhenTheSameOrderNumberArrivesWithOtherContent(): void
    {
        $this->postSigned(self::payload());

        $this->postSigned(self::payload(totalGross: 1999, lines: [['sku' => 'PAL-EUR-1', 'name' => 'Euro pallet, new', 'quantity' => 1, 'unitPrice' => 1999]]));

        self::assertResponseStatusCodeSame(409);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        $existing = $this->json()['order'];
        self::assertIsArray($existing);
        self::assertSame('received', $existing['status']);
    }

    /**
     * @param list<array<string, mixed>>|null $lines
     *
     * @return array<string, mixed>
     */
    private static function payload(int $totalGross = 6548, ?array $lines = null): array
    {
        return [
            'source' => 'shopware',
            'orderNumber' => '10042',
            'currency' => 'EUR',
            'customer' => ['email' => 'jane@example.com', 'name' => 'Jane Doe'],
            'lines' => $lines ?? [
                ['sku' => 'PAL-EUR-1', 'name' => 'Euro pallet, new', 'quantity' => 2, 'unitPrice' => 1999],
                ['sku' => 'PAL-IND-2', 'name' => 'Industrial pallet', 'quantity' => 1, 'unitPrice' => 2550],
            ],
            'totalGross' => $totalGross,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postSigned(array $payload): void
    {
        $body = json_encode($payload, \JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/webhooks/orders', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE' => $this->verifier()->sign($body),
        ], content: $body);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(): array
    {
        /** @var array<string, mixed> */
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    private function verifier(): WebhookSignatureVerifier
    {
        /** @var WebhookSignatureVerifier */
        return static::getContainer()->get(WebhookSignatureVerifier::class);
    }

    private function asyncTransport(): InMemoryTransport
    {
        /** @var InMemoryTransport */
        return static::getContainer()->get('messenger.transport.async');
    }
}
