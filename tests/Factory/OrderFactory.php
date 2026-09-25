<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Order;

final class OrderFactory
{
    /**
     * @param list<array{sku: string, name: string, quantity: int, unitPrice: int}>|null $lines
     */
    public static function create(string $externalId = '10042', ?array $lines = null): Order
    {
        $lines ??= [
            ['sku' => 'PAL-EUR-1', 'name' => 'Euro pallet, new', 'quantity' => 2, 'unitPrice' => 1999],
            ['sku' => 'PAL-IND-2', 'name' => 'Industrial pallet', 'quantity' => 1, 'unitPrice' => 2550],
        ];

        $total = array_sum(array_map(static fn (array $l): int => $l['quantity'] * $l['unitPrice'], $lines));

        return new Order(
            source: 'shopware',
            externalId: $externalId,
            customerEmail: 'jane@example.com',
            customerName: 'Jane Doe',
            currency: 'EUR',
            totalGross: $total,
            lines: $lines,
            payloadHash: hash('sha256', $externalId),
        );
    }
}
