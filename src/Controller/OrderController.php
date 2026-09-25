<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Presenter\OrderPresenter;
use App\Repository\OrderRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Read API for support and monitoring. Protected by a bearer token
 * (see security.yaml and App\Security\ApiTokenHandler).
 */
#[Route('/api/orders', format: 'json')]
final class OrderController extends AbstractController
{
    public function __construct(
        private readonly OrderPresenter $presenter,
    ) {
    }

    #[Route('', name: 'orders_list', methods: ['GET'])]
    public function list(
        OrderRepository $orders,
        #[MapQueryParameter] ?OrderStatus $status = null,
        #[MapQueryParameter(options: ['min_range' => 1, 'max_range' => 100])] int $limit = 20,
    ): JsonResponse {
        return $this->json([
            'items' => array_map($this->presenter->summary(...), $orders->findLatest($status, $limit)),
        ]);
    }

    #[Route('/{id}', name: 'orders_show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(#[MapEntity(id: 'id')] Order $order): JsonResponse
    {
        return $this->json($this->presenter->detail($order));
    }
}
