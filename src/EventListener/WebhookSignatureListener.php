<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Security\RequiresWebhookSignature;
use App\Security\WebhookSignatureVerifier;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Runs on kernel.controller, i.e. after routing but before the request
 * payload is mapped and validated: an unsigned request gets a 401 and
 * never reaches the serializer.
 */
#[AsEventListener(event: KernelEvents::CONTROLLER)]
final class WebhookSignatureListener
{
    public function __construct(
        private readonly WebhookSignatureVerifier $verifier,
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        if ([] === $event->getAttributes(RequiresWebhookSignature::class)) {
            return;
        }

        $request = $event->getRequest();

        if (!$this->verifier->isValid($request->getContent(), $request->headers->get(RequiresWebhookSignature::HEADER))) {
            throw new UnauthorizedHttpException('HMAC-SHA256', 'Missing or invalid webhook signature.');
        }
    }
}
