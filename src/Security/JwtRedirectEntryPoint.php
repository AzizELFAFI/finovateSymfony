<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Cookie;
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

        // Pure API calls → return JSON 401
        if (str_starts_with($path, '/api')) {
            return new JsonResponse(['code' => 401, 'message' => 'JWT Token not found'], 401);
        }

        $accept = (string) $request->headers->get('Accept', '');
        if (str_contains($accept, 'application/json') && !str_contains($accept, 'text/html')) {
            return new JsonResponse(['code' => 401, 'message' => 'JWT Token not found'], 401);
        }

        // Browser request with expired/invalid token → clear cookie and redirect to login
        $url = $this->urlGenerator->generate('front_login');
        $response = new RedirectResponse($url);

        // Expire the JWT cookie so the browser stops sending it
        $response->headers->setCookie(
            Cookie::create('finovate_token')
                ->withValue('')
                ->withExpires(1)
                ->withPath('/')
                ->withHttpOnly(true)
                ->withSameSite('lax')
        );

        return $response;
    }
}
