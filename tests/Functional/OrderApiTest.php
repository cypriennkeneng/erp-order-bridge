<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Factory\OrderFactory;
use App\Tests\Support\RecreatesDatabaseSchema;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OrderApiTest extends WebTestCase
{
    use RecreatesDatabaseSchema;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        self::recreateSchema(self::entityManager());
    }

    public function testRequiresABearerToken(): void
    {
        $this->client->request('GET', '/api/orders');
        self::assertResponseStatusCodeSame(401);

        $this->client->request('GET', '/api/orders', server: ['HTTP_AUTHORIZATION' => 'Bearer wrong-token']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testListsOrdersAndFiltersByStatus(): void
    {
        $exported = OrderFactory::create('10001');
        $exported->markExported('SO-2026-00001');
        $failed = OrderFactory::create('10002');
        $failed->markFailed('Article unknown');
        $em = self::entityManager();
        $em->persist($exported);
        $em->persist($failed);
        $em->flush();

        $all = $this->getJson('/api/orders')['items'];
        self::assertIsArray($all);
        self::assertCount(2, $all);

        $onlyFailed = $this->getJson('/api/orders?status=failed')['items'];
        self::assertIsArray($onlyFailed);
        self::assertCount(1, $onlyFailed);
        self::assertIsArray($onlyFailed[0]);
        self::assertSame('10002', $onlyFailed[0]['orderNumber']);
    }

    public function testShowsAnOrderWithItsHistory(): void
    {
        $order = OrderFactory::create('10042');
        $order->registerExportAttempt();
        $order->deferExport('ERP answered HTTP 503.');
        $order->registerExportAttempt();
        $order->markExported('SO-2026-00042');
        $em = self::entityManager();
        $em->persist($order);
        $em->flush();

        $body = $this->getJson('/api/orders/'.$order->getId()->toRfc4122());

        self::assertSame('exported', $body['status']);
        self::assertSame('SO-2026-00042', $body['erpReference']);
        self::assertSame(2, $body['exportAttempts']);
        self::assertIsArray($body['history']);
        self::assertSame(
            ['received', 'export_attempted', 'export_deferred', 'export_attempted', 'exported'],
            array_column($body['history'], 'type'),
        );
    }

    public function testReturns404ForAnUnknownOrder(): void
    {
        $this->client->request('GET', '/api/orders/0199a1b2-0000-7000-8000-000000000000', server: $this->auth());

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return array<string, mixed>
     */
    private function getJson(string $uri): array
    {
        $this->client->request('GET', $uri, server: $this->auth());
        self::assertResponseIsSuccessful();

        /** @var array<string, mixed> */
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, string>
     */
    private function auth(): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer test-api-token'];
    }
}
