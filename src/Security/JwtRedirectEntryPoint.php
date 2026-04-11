<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class JwtRedirectEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): JsonResponse|RedirectResponse
    {
        $path = (string) $request->getPathInfo();

        if (str_starts_with($path, '/api')) {
            return new JsonResponse(['message' => 'JWT Token not found'], 401);
        }

        $accept = (string) $request->headers->get('Accept', '');
        if (str_contains($accept, 'application/json')) {
            return new JsonResponse(['message' => 'JWT Token not found'], 401);
        }

        if (str_starts_with($path, '/admin')) {
            $url = $this->urlGenerator->generate('front_login');
            return new RedirectResponse($url);
        }

        $url = $this->urlGenerator->generate('front_login');
        return new RedirectResponse($url);
    }
}
