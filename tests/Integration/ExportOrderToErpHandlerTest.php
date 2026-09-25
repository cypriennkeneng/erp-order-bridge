<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Erp\ErpOrderMapper;
use App\Erp\Exception\ErpRejectedException;
use App\Erp\Exception\ErpUnavailableException;
use App\Message\ExportOrderToErp;
use App\MessageHandler\ExportOrderToErpHandler;
use App\Repository\OrderRepository;
use App\Tests\Factory\OrderFactory;
use App\Tests\Support\FakeErpClient;
use App\Tests\Support\RecreatesDatabaseSchema;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

final class ExportOrderToErpHandlerTest extends KernelTestCase
{
    use RecreatesDatabaseSchema;

    private FakeErpClient $erp;
    private ExportOrderToErpHandler $handler;

    protected function setUp(): void
    {
        self::bootKernel();
        self::recreateSchema(self::entityManager());

        $this->erp = new FakeErpClient();
        /** @var OrderRepository $repository */
        $repository = self::entityManager()->getRepository(Order::class);
        $this->handler = new ExportOrderToErpHandler($repository, self::entityManager(), new ErpOrderMapper(), $this->erp, new NullLogger());
    }

    public function testExportsTheOrderAndStoresTheErpReference(): void
    {
        $order = $this->storedOrder();
        $this->erp->willReturn('SO-2026-00042');

        ($this->handler)(new ExportOrderToErp($order->getId()->toRfc4122()));

        $order = $this->reload($order);
        self::assertSame(OrderStatus::Exported, $order->getStatus());
        self::assertSame('SO-2026-00042', $order->getErpReference());
        self::assertSame(1, $order->getExportAttempts());
        self::assertSame($order->getId()->toRfc4122(), $this->erp->calls[0]['idempotencyKey']);
        self::assertSame('10042', $this->erp->calls[0]['payload']['externalNumber']);
    }

    public function testKeepsTheOrderPendingAndRethrowsWhenTheErpIsUnavailable(): void
    {
        $order = $this->storedOrder();
        $this->erp->willThrow(new ErpUnavailableException('ERP answered HTTP 503.'));

        try {
            ($this->handler)(new ExportOrderToErp($order->getId()->toRfc4122()));
            self::fail('Expected the exception to reach Messenger so it retries.');
        } catch (ErpUnavailableException) {
        }

        $order = $this->reload($order);
        self::assertSame(OrderStatus::Received, $order->getStatus());
        self::assertSame('ERP answered HTTP 503.', $order->getLastError());
        self::assertSame(1, $order->getExportAttempts());
    }

    public function testMarksTheOrderFailedAndStopsRetryingWhenTheErpRejectsIt(): void
    {
        $order = $this->storedOrder();
        $this->erp->willThrow(new ErpRejectedException('Article "PAL-EUR-1" does not exist.'));

        try {
            ($this->handler)(new ExportOrderToErp($order->getId()->toRfc4122()));
            self::fail('Expected an unrecoverable exception.');
        } catch (UnrecoverableMessageHandlingException) {
        }

        $order = $this->reload($order);
        self::assertSame(OrderStatus::Failed, $order->getStatus());
        self::assertSame('Article "PAL-EUR-1" does not exist.', $order->getLastError());
    }

    public function testIgnoresAMessageForAnAlreadyExportedOrder(): void
    {
        $order = OrderFactory::create();
        $order->markExported('SO-2026-00001');
        $this->persist($order);

        ($this->handler)(new ExportOrderToErp($order->getId()->toRfc4122()));

        self::assertSame([], $this->erp->calls);
    }

    public function testIgnoresAMessageForADeletedOrder(): void
    {
        ($this->handler)(new ExportOrderToErp('0199a1b2-0000-7000-8000-000000000000'));

        self::assertSame([], $this->erp->calls);
    }

    private function storedOrder(): Order
    {
        $order = OrderFactory::create('10042');
        $this->persist($order);

        return $order;
    }

    private function persist(Order $order): void
    {
        $em = self::entityManager();
        $em->persist($order);
        $em->flush();
    }

    private function reload(Order $order): Order
    {
        $em = self::entityManager();
        $em->clear();

        return $em->find(Order::class, $order->getId()) ?? throw new \RuntimeException('Order vanished.');
    }
}
