<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException as KernelAccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AccessDeniedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        $path = (string) $request->getPathInfo();
        if (str_starts_with($path, '/api')) {
            return;
        }

        $exception = $event->getThrowable();

        $is403 = $exception instanceof AccessDeniedException
            || $exception instanceof KernelAccessDeniedHttpException
            || ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 403);

        if (!$is403) {
            return;
        }

        if ($path === '/access-denied') {
            return;
        }

        $accept = (string) $request->headers->get('Accept', '');
        if (str_contains($accept, 'application/json')) {
            return;
        }

        $url = $this->urlGenerator->generate('app_access_denied');
        $event->setResponse(new RedirectResponse($url));
    }
}
