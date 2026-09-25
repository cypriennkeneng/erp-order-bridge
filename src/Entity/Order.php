<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OrderEventType;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * An order received from a shop, and the state of its export to the ERP.
 *
 * @phpstan-type OrderLine array{sku: string, name: string, quantity: int, unitPrice: int}
 */
#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'shop_order')]
#[ORM\UniqueConstraint(name: 'uniq_order_source_external_id', columns: ['source', 'external_id'])]
#[ORM\Index(name: 'idx_order_status', columns: ['status'])]
class Order
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 32, enumType: OrderStatus::class)]
    private OrderStatus $status = OrderStatus::Received;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $erpReference = null;

    #[ORM\Column]
    private int $exportAttempts = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, OrderEvent> */
    #[ORM\OneToMany(targetEntity: OrderEvent::class, mappedBy: 'order', cascade: ['persist'], orphanRemoval: true)]
    private Collection $events;

    /**
     * @param list<OrderLine> $lines amounts in minor units (cents)
     */
    public function __construct(
        #[ORM\Column(length: 32)]
        private string $source,
        #[ORM\Column(length: 64)]
        private string $externalId,
        #[ORM\Column(length: 180)]
        private string $customerEmail,
        #[ORM\Column(length: 180)]
        private string $customerName,
        #[ORM\Column(length: 3)]
        private string $currency,
        #[ORM\Column]
        private int $totalGross,
        #[ORM\Column(type: Types::JSON)]
        private array $lines,
        #[ORM\Column(length: 64)]
        private string $payloadHash,
    ) {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->events = new ArrayCollection();
        $this->record(OrderEventType::Received);
    }

    public function registerExportAttempt(): void
    {
        ++$this->exportAttempts;
        $this->record(OrderEventType::ExportAttempted, \sprintf('Attempt #%d', $this->exportAttempts));
    }

    public function markExported(string $erpReference): void
    {
        $this->status = OrderStatus::Exported;
        $this->erpReference = $erpReference;
        $this->lastError = null;
        $this->record(OrderEventType::Exported, $erpReference);
    }

    /** The ERP was unreachable; the export will be retried. */
    public function deferExport(string $reason): void
    {
        $this->lastError = $reason;
        $this->record(OrderEventType::ExportDeferred, $reason);
    }

    public function markFailed(string $reason): void
    {
        $this->status = OrderStatus::Failed;
        $this->lastError = $reason;
        $this->record(OrderEventType::ExportFailed, $reason);
    }

    /** Puts a failed order back in the queue, e.g. after fixing master data in the ERP. */
    public function requeue(): void
    {
        if (OrderStatus::Failed !== $this->status) {
            throw new \LogicException(\sprintf('Only failed orders can be requeued, order %s is "%s".', $this->id, $this->status->value));
        }

        $this->status = OrderStatus::Received;
        $this->record(OrderEventType::Requeued);
    }

    public function isExported(): bool
    {
        return OrderStatus::Exported === $this->status;
    }

    private function record(OrderEventType $type, ?string $message = null): void
    {
        $this->events->add(new OrderEvent($this, $type, $message));
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getCustomerEmail(): string
    {
        return $this->customerEmail;
    }

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getTotalGross(): int
    {
        return $this->totalGross;
    }

    /** @return list<OrderLine> */
    public function getLines(): array
    {
        return $this->lines;
    }

    public function getPayloadHash(): string
    {
        return $this->payloadHash;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function getErpReference(): ?string
    {
        return $this->erpReference;
    }

    public function getExportAttempts(): int
    {
        return $this->exportAttempts;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return list<OrderEvent> oldest first */
    public function getEvents(): array
    {
        $events = array_values($this->events->toArray());
        // By id (insertion order); events not yet flushed have no id and are the newest.
        usort($events, static fn (OrderEvent $a, OrderEvent $b): int => ($a->getId() ?? \PHP_INT_MAX) <=> ($b->getId() ?? \PHP_INT_MAX));

        return $events;
    }
}
