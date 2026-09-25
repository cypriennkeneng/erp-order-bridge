<?php

declare(strict_types=1);

namespace App\Tests\Unit\Erp;

use App\Erp\ErpOrderMapper;
use App\Tests\Factory\OrderFactory;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class ErpOrderMapperTest extends TestCase
{
    public function testMapsAnOrderToAnErpSalesOrder(): void
    {
        $order = OrderFactory::create('10042');

        $payload = (new ErpOrderMapper())->toSalesOrder($order);

        self::assertSame('SALES_ORDER', $payload['documentType']);
        self::assertSame('10042', $payload['externalNumber']);
        self::assertSame('SHOPWARE', $payload['channel']);
        self::assertSame('EUR', $payload['currency']);
        self::assertSame(['email' => 'jane@example.com', 'name' => 'Jane Doe'], $payload['customer']);
        self::assertSame('65.48', $payload['totalGross']);
        self::assertSame([
            ['position' => 10, 'articleNumber' => 'PAL-EUR-1', 'description' => 'Euro pallet, new', 'quantity' => 2, 'unitPrice' => '19.99'],
            ['position' => 20, 'articleNumber' => 'PAL-IND-2', 'description' => 'Industrial pallet', 'quantity' => 1, 'unitPrice' => '25.50'],
        ], $payload['lines']);
    }

    #[TestWith([0, '0.00'])]
    #[TestWith([5, '0.05'])]
    #[TestWith([1999, '19.99'])]
    #[TestWith([100000, '1000.00'])]
    public function testFormatsMinorUnitsWithoutFloats(int $minor, string $expected): void
    {
        self::assertSame($expected, ErpOrderMapper::decimal($minor));
    }

    public function testRejectsNegativeAmounts(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ErpOrderMapper::decimal(-1);
    }
}
