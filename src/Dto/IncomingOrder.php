<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Order as sent by the shop webhook. Amounts are integers in minor units (cents)
 * so no floating point ever touches money.
 */
final class IncomingOrder
{
    /**
     * @param list<IncomingOrderLine> $lines
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['shopware', 'shopify', 'woocommerce'])]
        public readonly string $source,
        #[Assert\NotBlank]
        #[Assert\Length(max: 64)]
        public readonly string $orderNumber,
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^[A-Z]{3}$/', message: 'Expected an ISO 4217 currency code such as EUR.')]
        public readonly string $currency,
        #[Assert\Valid]
        public readonly IncomingCustomer $customer,
        #[Assert\Count(min: 1, max: 500)]
        #[Assert\Valid]
        public readonly array $lines,
        #[Assert\PositiveOrZero]
        public readonly int $totalGross,
    ) {
    }

    #[Assert\Callback]
    public function validateTotal(ExecutionContextInterface $context): void
    {
        $sum = 0;
        foreach ($this->lines as $line) {
            $sum += $line->quantity * $line->unitPrice;
        }

        if ($sum !== $this->totalGross) {
            $context->buildViolation('totalGross ({{ total }}) does not match the sum of the lines ({{ sum }}).')
                ->setParameter('{{ total }}', (string) $this->totalGross)
                ->setParameter('{{ sum }}', (string) $sum)
                ->atPath('totalGross')
                ->addViolation();
        }
    }
}
