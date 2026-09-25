<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Enum\OrderStatus;
use App\Message\ExportOrderToErp;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Uid\Uuid;

/**
 * When the last retry has failed, the message moves to the "failed" transport.
 * This listener makes that visible on the order itself, so the read API and
 * app:orders:requeue-failed see it without anyone digging through the queue.
 */
#[AsEventListener]
final class MarkOrderFailedWhenRetriesExhausted
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();

        if ($event->willRetry() || !$message instanceof ExportOrderToErp) {
            return;
        }

        $order = $this->orders->findOneById(Uuid::fromString($message->orderId));

        if (null === $order || OrderStatus::Received !== $order->getStatus()) {
            return; // gone, exported, or already marked failed by the handler
        }

        $order->markFailed('Retries exhausted: '.$event->getThrowable()->getMessage());
        $this->entityManager->flush();
    }
}
