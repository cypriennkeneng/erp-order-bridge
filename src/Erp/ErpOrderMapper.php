<?php

declare(strict_types=1);

namespace App\Erp;

use App\Entity\Order;

/**
 * Translates the shop-side order into the ERP's sales-order format.
 * Kept separate from the HTTP client so the mapping can be unit-tested
 * and adapted per ERP without touching transport code.
 */
final class ErpOrderMapper
{
    /**
     * @return array{
     *     documentType: string,
     *     externalNumber: string,
     *     channel: string,
     *     orderDate: string,
     *     currency: string,
     *     customer: array{email: string, name: string},
     *     lines: list<array{position: int, articleNumber: string, description: string, quantity: int, unitPrice: string}>,
     *     totalGross: string
     * }
     */
    public function toSalesOrder(Order $order): array
    {
        $lines = [];
        foreach ($order->getLines() as $index => $line) {
            $lines[] = [
                'position' => ($index + 1) * 10, // ERP convention: 10, 20, 30...
                'articleNumber' => $line['sku'],
                'description' => $line['name'],
                'quantity' => $line['quantity'],
                'unitPrice' => self::decimal($line['unitPrice']),
            ];
        }

        return [
            'documentType' => 'SALES_ORDER',
            'externalNumber' => $order->getExternalId(),
            'channel' => strtoupper($order->getSource()),
            'orderDate' => $order->getCreatedAt()->format('Y-m-d'),
            'currency' => $order->getCurrency(),
            'customer' => [
                'email' => $order->getCustomerEmail(),
                'name' => $order->getCustomerName(),
            ],
            'lines' => $lines,
            'totalGross' => self::decimal($order->getTotalGross()),
        ];
    }

    /**
     * 1999 -> "19.99". Integer arithmetic only, no float rounding.
     */
    public static function decimal(int $minorUnits): string
    {
        if ($minorUnits < 0) {
            throw new \InvalidArgumentException('Negative amounts are not supported.');
        }

        return \sprintf('%d.%02d', intdiv($minorUnits, 100), $minorUnits % 100);
    }
}
