<?php

declare(strict_types=1);

namespace App\Erp;

use App\Erp\Exception\ErpRejectedException;
use App\Erp\Exception\ErpUnavailableException;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Talks to the ERP's REST API through the "erp.client" scoped client
 * (base URL, bearer token and timeout are configured in framework.yaml).
 */
final class HttpErpClient implements ErpClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $erpClient,
    ) {
    }

    public function createSalesOrder(array $payload, string $idempotencyKey): string
    {
        try {
            $response = $this->erpClient->request('POST', 'sales-orders', [
                'json' => $payload,
                // Lets the ERP recognise a retry after a timeout instead of
                // creating the same sales order twice.
                'headers' => ['Idempotency-Key' => $idempotencyKey],
            ]);

            $status = $response->getStatusCode();
        } catch (ExceptionInterface $e) {
            throw new ErpUnavailableException('ERP not reachable: '.$e->getMessage(), previous: $e);
        }

        try {
            $body = $response->toArray(throw: false);
        } catch (DecodingExceptionInterface) {
            $body = []; // e.g. an HTML error page from a proxy
        } catch (ExceptionInterface $e) {
            throw new ErpUnavailableException('ERP response could not be read: '.$e->getMessage(), previous: $e);
        }

        // 409: the ERP already processed this idempotency key and returns the original document.
        if (\in_array($status, [200, 201, 409], true) && \is_string($body['documentNumber'] ?? null)) {
            return $body['documentNumber'];
        }

        if (429 === $status || $status >= 500) {
            throw new ErpUnavailableException(\sprintf('ERP answered HTTP %d.', $status));
        }

        if ($status >= 400) {
            throw new ErpRejectedException(\sprintf('ERP rejected the order (HTTP %d): %s', $status, self::errorMessage($body)));
        }

        throw new ErpUnavailableException(\sprintf('Unexpected ERP response (HTTP %d) without document number.', $status));
    }

    /**
     * @param array<mixed> $body
     */
    private static function errorMessage(array $body): string
    {
        $message = $body['message'] ?? null;

        return \is_string($message) ? mb_substr($message, 0, 500) : 'no details';
    }
}
