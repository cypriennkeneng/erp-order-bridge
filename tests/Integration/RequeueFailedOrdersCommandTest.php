<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Tests\Factory\OrderFactory;
use App\Tests\Support\RecreatesDatabaseSchema;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class RequeueFailedOrdersCommandTest extends KernelTestCase
{
    use RecreatesDatabaseSchema;

    private CommandTester $tester;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        self::recreateSchema(self::entityManager());
        $this->tester = new CommandTester((new Application($kernel))->find('app:orders:requeue-failed'));
    }

    public function testRequeuesFailedOrdersOnly(): void
    {
        $failed = OrderFactory::create('10001');
        $failed->markFailed('Article unknown');
        $exported = OrderFactory::create('10002');
        $exported->markExported('SO-1');
        $this->persist($failed, $exported);

        $this->tester->execute([]);

        $this->tester->assertCommandIsSuccessful();
        self::assertStringContainsString('1 order(s) requeued', $this->tester->getDisplay());
        self::assertSame(OrderStatus::Received, $this->reload($failed)->getStatus());
        self::assertCount(1, $this->asyncTransport()->getSent());
    }

    public function testDryRunChangesNothing(): void
    {
        $failed = OrderFactory::create('10001');
        $failed->markFailed('Article unknown');
        $this->persist($failed);

        $this->tester->execute(['--dry-run' => true]);

        $this->tester->assertCommandIsSuccessful();
        self::assertSame(OrderStatus::Failed, $this->reload($failed)->getStatus());
        self::assertCount(0, $this->asyncTransport()->getSent());
    }

    private function persist(Order ...$orders): void
    {
        $em = self::entityManager();
        foreach ($orders as $order) {
            $em->persist($order);
        }
        $em->flush();
    }

    private function reload(Order $order): Order
    {
        $em = self::entityManager();
        $em->clear();

        return $em->find(Order::class, $order->getId()) ?? throw new \RuntimeException('Order vanished.');
    }

    private function asyncTransport(): InMemoryTransport
    {
        /** @var InMemoryTransport */
        return self::getContainer()->get('messenger.transport.async');
    }
}
