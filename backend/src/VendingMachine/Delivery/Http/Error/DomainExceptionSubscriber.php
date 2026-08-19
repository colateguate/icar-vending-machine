<?php

declare(strict_types=1);

namespace App\VendingMachine\Delivery\Http\Error;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The one place where a failure becomes a response.
 *
 * Controllers therefore catch nothing: a domain refusal travels up as an
 * exception, which is what lets a use case say "sold out" once and have it
 * mean the same thing over HTTP, over the CLI, and in a test. A try/catch in
 * each controller would be the same table copied five times, drifting.
 *
 * It answers for every exception rather than only the domain's, because this
 * application serves JSON and nothing else. An API whose refusals are
 * problem+json but whose unknown route is an HTML error page has two
 * contracts, and clients meet the second one in production.
 */
final readonly class DomainExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(private ProblemDetailsFactory $problems)
    {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onKernelException'];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $event->setResponse($this->problems->fromThrowable($event->getThrowable()));
    }
}
