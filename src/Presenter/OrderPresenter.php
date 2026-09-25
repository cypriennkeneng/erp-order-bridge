<?php

declare(strict_types=1);

namespace App\Presenter;

use App\Entity\Order;
use App\Erp\ErpOrderMapper;

/**
 * Explicit JSON shape of the API. Entities are never serialized directly,
 * so a new Doctrine field cannot leak into responses by accident.
 */
final class OrderPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function summary(Order $order): array
    {
        return [
            'id' => $order->getId()->toRfc4122(),
            'source' => $order->getSource(),
            'orderNumber' => $order->getExternalId(),
            'status' => $order->getStatus()->value,
            'erpReference' => $order->getErpReference(),
            'totalGross' => ErpOrderMapper::decimal($order->getTotalGross()),
            'currency' => $order->getCurrency(),
            'createdAt' => $order->getCreatedAt()->format(\DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Order $order): array
    {
        return $this->summary($order) + [
            'exportAttempts' => $order->getExportAttempts(),
            'lastError' => $order->getLastError(),
            'customer' => [
                'email' => $order->getCustomerEmail(),
                'name' => $order->getCustomerName(),
            ],
            'lines' => $order->getLines(),
            'history' => array_map(static fn ($event): array => [
                'type' => $event->getType()->value,
                'message' => $event->getMessage(),
                'occurredAt' => $event->getOccurredAt()->format(\DATE_ATOM),
            ], $order->getEvents()),
        ];
    }
}
