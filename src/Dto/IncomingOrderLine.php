<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class IncomingOrderLine
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 64)]
        public readonly string $sku,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public readonly string $name,
        #[Assert\Positive]
        public readonly int $quantity,
        #[Assert\PositiveOrZero]
        public readonly int $unitPrice,
    ) {
    }
}
