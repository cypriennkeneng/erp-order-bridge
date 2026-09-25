<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\OrderStatus;
use App\Message\ExportOrderToErp;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Typical use: the ERP rejected orders because an article was missing;
 * once the article exists in the ERP, requeue them in one go.
 */
#[AsCommand(name: 'app:orders:requeue-failed', description: 'Puts failed orders back into the export queue')]
final class RequeueFailedOrdersCommand
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Maximum number of orders to requeue')] int $limit = 100,
        #[Option(description: 'Only list the orders, change nothing')] bool $dryRun = false,
    ): int {
        $failed = $this->orders->findLatest(OrderStatus::Failed, $limit);

        if ([] === $failed) {
            $io->success('No failed orders.');

            return Command::SUCCESS;
        }

        $io->table(
            ['Order', 'Source', 'Attempts', 'Last error'],
            array_map(static fn ($order): array => [
                $order->getExternalId(),
                $order->getSource(),
                $order->getExportAttempts(),
                mb_strimwidth($order->getLastError() ?? '', 0, 80, '…'),
            ], $failed),
        );

        if ($dryRun) {
            $io->note(\sprintf('Dry run: %d order(s) would be requeued.', \count($failed)));

            return Command::SUCCESS;
        }

        foreach ($failed as $order) {
            $order->requeue();
        }
        $this->entityManager->flush();

        foreach ($failed as $order) {
            $this->bus->dispatch(new ExportOrderToErp($order->getId()->toRfc4122()));
        }

        $io->success(\sprintf('%d order(s) requeued.', \count($failed)));

        return Command::SUCCESS;
    }
}
