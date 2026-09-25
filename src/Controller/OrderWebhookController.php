<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\IncomingOrder;
use App\Ingestion\OrderConflictException;
use App\Ingestion\OrderIngestion;
use App\Presenter\OrderPresenter;
use App\Security\RequiresWebhookSignature;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class OrderWebhookController extends AbstractController
{
    /**
     * 202 new order accepted and queued
     * 200 duplicate delivery, nothing changed
     * 401 missing/invalid signature
     * 409 same order number, different content
     * 422 invalid payload.
     */
    #[Route('/api/webhooks/orders', name: 'webhook_orders', methods: ['POST'], format: 'json')]
    #[RequiresWebhookSignature]
    public function __invoke(
        #[MapRequestPayload(acceptFormat: 'json')] IncomingOrder $incoming,
        OrderIngestion $ingestion,
        OrderPresenter $presenter,
    ): JsonResponse {
        try {
            $result = $ingestion->ingest($incoming);
        } catch (OrderConflictException $e) {
            return $this->json([
                'type' => 'https://tools.ietf.org/html/rfc9110#section-15.5.10',
                'title' => 'Conflict',
                'detail' => $e->getMessage(),
                'order' => $presenter->summary($e->existing),
            ], Response::HTTP_CONFLICT, ['Content-Type' => 'application/problem+json']);
        }

        return $this->json(
            $presenter->summary($result->order),
            $result->created ? Response::HTTP_ACCEPTED : Response::HTTP_OK,
        );
    }
}
