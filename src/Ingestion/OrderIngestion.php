<?php

declare(strict_types=1);

namespace App\Ingestion;

use App\Dto\IncomingOrder;
use App\Entity\Order;
use App\Message\ExportOrderToErp;
use App\Repository\OrderRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Stores an incoming order exactly once and queues its export.
 *
 * Shops retry webhooks whenever they do not get a timely 2xx, so the same
 * order routinely arrives several times. (source, orderNumber) is the
 * idempotency key: a repeat with identical content is acknowledged without
 * side effects, a repeat with different content is a conflict.
 */
final class OrderIngestion
{
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * @throws OrderConflictException
     */
    public function ingest(IncomingOrder $incoming): IngestionResult
    {
        $hash = self::hash($incoming);

        $existing = $this->repository()->findOneBySourceAndExternalId($incoming->source, $incoming->orderNumber);
        if (null !== $existing) {
            return $this->duplicate($existing, $hash);
        }

        $order = new Order(
            source: $incoming->source,
            externalId: $incoming->orderNumber,
            customerEmail: $incoming->customer->email,
            customerName: $incoming->customer->name,
            currency: $incoming->currency,
            totalGross: $incoming->totalGross,
            lines: array_map(static fn ($line): array => [
                'sku' => $line->sku,
                'name' => $line->name,
                'quantity' => $line->quantity,
                'unitPrice' => $line->unitPrice,
            ], $incoming->lines),
            payloadHash: $hash,
        );

        try {
            // With the Doctrine transport the queued message is written in the
            // same transaction as the order: either both exist or neither does.
            $this->entityManager()->wrapInTransaction(function (EntityManagerInterface $em) use ($order): void {
                $em->persist($order);
                $em->flush();
                $this->bus->dispatch(new ExportOrderToErp($order->getId()->toRfc4122()));
            });
        } catch (UniqueConstraintViolationException) {
            // Two deliveries of the same webhook raced each other and the other one won.
            $this->registry->resetManager();
            $existing = $this->repository()->findOneBySourceAndExternalId($incoming->source, $incoming->orderNumber)
                ?? throw new \LogicException('Unique constraint violated but no existing order found.');

            return $this->duplicate($existing, $hash);
        }

        return new IngestionResult($order, created: true);
    }

    private function duplicate(Order $existing, string $hash): IngestionResult
    {
        if ($existing->getPayloadHash() !== $hash) {
            throw new OrderConflictException($existing);
        }

        return new IngestionResult($existing, created: false);
    }

    private static function hash(IncomingOrder $incoming): string
    {
        return hash('sha256', json_encode($incoming, \JSON_THROW_ON_ERROR));
    }

    private function repository(): OrderRepository
    {
        /** @var OrderRepository */
        return $this->entityManager()->getRepository(Order::class);
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface */
        return $this->registry->getManagerForClass(Order::class);
    }
}
