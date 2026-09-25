<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Erp\ErpClientInterface;
use App\Erp\ErpOrderMapper;
use App\Erp\Exception\ErpRejectedException;
use App\Erp\Exception\ErpUnavailableException;
use App\Message\ExportOrderToErp;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

/**
 * Pushes one order to the ERP.
 *
 * - ERP unavailable  -> exception rethrown, Messenger retries with backoff (max 5)
 * - ERP rejected     -> order marked failed, UnrecoverableMessageHandlingException (no retry)
 * - already exported -> no-op, so a message delivered twice is harmless
 */
#[AsMessageHandler]
final class ExportOrderToErpHandler
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly EntityManagerInterface $entityManager,
        private readonly ErpOrderMapper $mapper,
        private readonly ErpClientInterface $erp,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ExportOrderToErp $message): void
    {
        $order = $this->orders->findOneById(Uuid::fromString($message->orderId));

        if (null === $order) {
            $this->logger->warning('Order {id} no longer exists, export skipped.', ['id' => $message->orderId]);

            return;
        }

        if ($order->isExported()) {
            return;
        }

        $order->registerExportAttempt();

        try {
            $reference = $this->erp->createSalesOrder(
                $this->mapper->toSalesOrder($order),
                idempotencyKey: $order->getId()->toRfc4122(),
            );
        } catch (ErpUnavailableException $e) {
            $order->deferExport($e->getMessage());
            $this->entityManager->flush();

            // Rethrown as is: Messenger applies the bounded retry strategy from
            // messenger.yaml. (A RecoverableMessageHandlingException would
            // bypass max_retries and retry forever.)
            throw $e;
        } catch (ErpRejectedException $e) {
            $order->markFailed($e->getMessage());
            $this->entityManager->flush();
            $this->logger->error('ERP rejected order {external}: {reason}', [
                'external' => $order->getExternalId(),
                'reason' => $e->getMessage(),
            ]);

            throw new UnrecoverableMessageHandlingException($e->getMessage(), previous: $e);
        }

        $order->markExported($reference);
        $this->entityManager->flush();

        $this->logger->info('Order {external} exported as {reference}.', [
            'external' => $order->getExternalId(),
            'reference' => $reference,
        ]);
    }
}
