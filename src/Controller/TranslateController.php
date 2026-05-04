<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class TranslateController extends AbstractController
{
    #[Route('/api/translate', name: 'api_translate', methods: ['POST'])]
    public function translate(
        Request $request,
        HttpClientInterface $httpClient,
        #[Autowire(service: 'limiter.api_translate')] RateLimiterFactory $apiTranslateLimiter
    ): JsonResponse
    {
        $limiterKey = $request->getClientIp() ?: 'unknown';
        $limit = $apiTranslateLimiter->create($limiterKey)->consume(1);
        if (!$limit->isAccepted()) {
            return $this->json(['message' => 'Trop de requêtes. Veuillez réessayer plus tard.'], 429);
        }

        $payload = json_decode($request->getContent() ?: '', true);
        if (!is_array($payload)) {
            return $this->json(['message' => 'Payload JSON invalide.'], 400);
        }

        $text = trim((string) ($payload['text'] ?? ''));
        $target = strtolower(trim((string) ($payload['target'] ?? '')));
        $source = strtolower(trim((string) ($payload['source'] ?? 'auto')));

        if ($text === '' || $target === '') {
            return $this->json(['message' => 'Champs requis manquants.'], 422);
        }

        $baseUrl = trim((string) $this->getParameter('libretranslate_url'));
        if ($baseUrl === '') {
            return $this->json([
                'message' => 'Traduction non configurée (LIBRETRANSLATE_URL manquant).',
            ], 501);
        }

        $fallbackRaw = trim((string) $this->getParameter('libretranslate_fallback_urls'));
        $fallbacks = [];
        if ($fallbackRaw !== '') {
            foreach (preg_split('/[,\s]+/', $fallbackRaw) ?: [] as $u) {
                $u = trim((string) $u);
                if ($u !== '') {
                    $fallbacks[] = $u;
                }
            }
        }

        $candidateBaseUrls = array_values(array_unique(array_filter(array_merge([$baseUrl], $fallbacks))));

        $apiKey = trim((string) $this->getParameter('libretranslate_api_key'));

        $lastError = null;
        foreach ($candidateBaseUrls as $candidate) {
            try {
                $url = rtrim((string) $candidate, '/') . '/translate';

                $response = $httpClient->request('POST', $url, [
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                    'body' => array_filter([
                        'q' => $text,
                        'source' => $source === '' ? 'auto' : $source,
                        'target' => $target,
                        'format' => 'text',
                        'api_key' => $apiKey !== '' ? $apiKey : null,
                    ], static fn ($v) => $v !== null),
                    'timeout' => 12,
                ]);

                $status = $response->getStatusCode();
                $content = $response->getContent(false);

                $data = json_decode($content ?: '', true);
                if (!is_array($data)) {
                    $snippet = trim((string) preg_replace('/\s+/', ' ', mb_substr((string) $content, 0, 180)));
                    $hint = $snippet !== '' ? ('Réponse: ' . $snippet) : 'Réponse vide.';
                    $lastError = 'Traduction impossible (réponse non-JSON) via ' . $candidate . '. HTTP ' . $status . '. ' . $hint;
                    continue;
                }

                $translated = (string) ($data['translatedText'] ?? '');
                if ($translated === '') {
                    $lastError = 'Réponse traduction invalide via ' . $candidate . '.';
                    continue;
                }

                return $this->json([
                    'translatedText' => $translated,
                    'provider' => (string) $candidate,
                ]);
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                continue;
            }
        }

        $msg = $lastError ? ('Service de traduction indisponible. ' . $lastError) : 'Service de traduction indisponible.';
        if ($this->getParameter('kernel.environment') !== 'dev') {
            $msg = 'Service de traduction indisponible.';
        }
        return $this->json(['message' => $msg], 502);

        // unreachable
    }
}

