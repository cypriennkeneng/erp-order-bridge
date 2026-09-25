<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A deliberately unreliable stand-in for a real ERP, registered in the dev
 * environment only. It lets you watch retries, rejections and idempotency
 * locally without any external system:
 *
 * - a configurable share of requests fails with 503 (FAKE_ERP_FAILURE_RATE)
 * - article numbers starting with "UNKNOWN-" are rejected with 422
 * - a repeated Idempotency-Key returns 409 with the original document number
 */
final class FakeErpController extends AbstractController
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        #[Autowire(env: 'ERP_API_TOKEN')] private readonly string $expectedToken,
        #[Autowire(env: 'float:FAKE_ERP_FAILURE_RATE')] private readonly float $failureRate,
    ) {
    }

    #[Route('/_fake-erp/sales-orders', name: 'fake_erp_sales_orders', methods: ['POST'], env: 'dev')]
    public function createSalesOrder(Request $request): JsonResponse
    {
        if ($request->headers->get('Authorization') !== 'Bearer '.$this->expectedToken) {
            return $this->json(['message' => 'Invalid token.'], Response::HTTP_UNAUTHORIZED);
        }

        $key = (string) $request->headers->get('Idempotency-Key');
        if ('' === $key) {
            return $this->json(['message' => 'Idempotency-Key header is required.'], Response::HTTP_BAD_REQUEST);
        }

        $item = $this->cache->getItem('fake_erp_'.hash('xxh128', $key));
        if ($item->isHit()) {
            return $this->json(['documentNumber' => $item->get()], Response::HTTP_CONFLICT);
        }

        if (mt_rand() / mt_getrandmax() < $this->failureRate) {
            return $this->json(['message' => 'ERP temporarily unavailable.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        /** @var array{lines?: list<array{articleNumber?: string}>} $payload */
        $payload = $request->toArray();
        foreach ($payload['lines'] ?? [] as $line) {
            if (str_starts_with($line['articleNumber'] ?? '', 'UNKNOWN-')) {
                return $this->json(
                    ['message' => \sprintf('Article "%s" does not exist.', $line['articleNumber'])],
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }
        }

        $documentNumber = \sprintf('SO-%s-%05d', date('Y'), random_int(1, 99999));
        $this->cache->save($item->set($documentNumber)->expiresAfter(86400));

        return $this->json(['documentNumber' => $documentNumber], Response::HTTP_CREATED);
    }
}
