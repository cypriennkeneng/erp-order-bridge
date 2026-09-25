<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OrderEventType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Append-only audit trail: what happened to an order, and when.
 */
#[ORM\Entity]
#[ORM\Table(name: 'shop_order_event')]
class OrderEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'events')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Order $order,
        #[ORM\Column(length: 32, enumType: OrderEventType::class)]
        private OrderEventType $type,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $message = null,
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getType(): OrderEventType
    {
        return $this->type;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
