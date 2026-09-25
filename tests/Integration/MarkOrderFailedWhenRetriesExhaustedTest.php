<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Message\ExportOrderToErp;
use App\Tests\Factory\OrderFactory;
use App\Tests\Support\RecreatesDatabaseSchema;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

final class MarkOrderFailedWhenRetriesExhaustedTest extends KernelTestCase
{
    use RecreatesDatabaseSchema;

    protected function setUp(): void
    {
        self::bootKernel();
        self::recreateSchema(self::entityManager());
    }

    public function testMarksTheOrderFailedAfterTheLastRetry(): void
    {
        $order = $this->storedOrder();

        $this->dispatchFailure($order, willRetry: false);

        self::assertSame(OrderStatus::Failed, $this->reload($order)->getStatus());
        self::assertStringStartsWith('Retries exhausted: ERP answered HTTP 503.', (string) $this->reload($order)->getLastError());
    }

    public function testLeavesTheOrderAloneWhileRetriesRemain(): void
    {
        $order = $this->storedOrder();

        $this->dispatchFailure($order, willRetry: true);

        self::assertSame(OrderStatus::Received, $this->reload($order)->getStatus());
    }

    /**
     * Goes through the real event dispatcher, so Messenger's own retry listener
     * decides "will retry" from the RedeliveryStamp and messenger.yaml, exactly
     * as it does in the worker.
     */
    private function dispatchFailure(Order $order, bool $willRetry): void
    {
        $envelope = new Envelope(new ExportOrderToErp($order->getId()->toRfc4122()));
        if (!$willRetry) {
            $envelope = $envelope->with(new RedeliveryStamp(5)); // max_retries reached
        }

        $event = new WorkerMessageFailedEvent($envelope, 'async', new \RuntimeException('ERP answered HTTP 503.'));

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->dispatch($event);

        self::assertSame($willRetry, $event->willRetry(), 'precondition: Messenger\'s retry decision');
    }

    private function storedOrder(): Order
    {
        $order = OrderFactory::create();
        $em = self::entityManager();
        $em->persist($order);
        $em->flush();

        return $order;
    }

    private function reload(Order $order): Order
    {
        $em = self::entityManager();
        $em->clear();

        return $em->find(Order::class, $order->getId()) ?? throw new \RuntimeException('Order vanished.');
    }
}
