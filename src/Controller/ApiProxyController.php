<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ApiProxyController extends AbstractController
{
    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    #[Route('/api/proxy/unsplash', name: 'api_proxy_unsplash', methods: ['GET'])]
    public function unsplash(Request $request): JsonResponse
    {
        $query = trim((string) $request->query->get('query', ''));
        if ($query === '') {
            return $this->json(['error' => 'Query required.'], 400);
        }

        $key = (string) $this->getParameter('unsplash_access_key');

        try {
            $response = $this->httpClient->request('GET', 'https://api.unsplash.com/search/photos', [
                'query' => [
                    'query'       => $query,
                    'per_page'    => 6,
                    'orientation' => 'landscape',
                ],
                'headers' => [
                    'Authorization' => 'Client-ID ' . $key,
                ],
                'timeout' => 8,
            ]);

            $data = $response->toArray(false);

            $results = array_map(fn(array $photo) => [
                'id'    => $photo['id'] ?? '',
                'thumb' => $photo['urls']['small'] ?? '',
                'full'  => $photo['urls']['regular'] ?? '',
                'alt'   => $photo['alt_description'] ?? $photo['description'] ?? '',
                'user'  => $photo['user']['name'] ?? '',
                'link'  => $photo['links']['html'] ?? '',
            ], $data['results'] ?? []);

            return $this->json(['results' => $results]);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Unsplash unavailable.'], 502);
        }
    }

    #[Route('/api/proxy/exchange', name: 'api_proxy_exchange', methods: ['GET'])]
    public function exchange(Request $request): JsonResponse
    {
        $base = strtoupper(trim((string) $request->query->get('base', 'USD')));
        $key  = (string) $this->getParameter('exchangerate_api_key');

        try {
            $response = $this->httpClient->request(
                'GET',
                sprintf('https://v6.exchangerate-api.com/v6/%s/latest/%s', $key, $base),
                ['timeout' => 8]
            );

            $data = $response->toArray(false);

            if (($data['result'] ?? '') !== 'success') {
                return $this->json(['error' => 'Exchange rate API error.'], 502);
            }

            $rates = $data['conversion_rates'] ?? [];
            $keep  = ['USD', 'EUR', 'GBP', 'TND', 'MAD', 'DZD', 'SAR', 'AED', 'CAD', 'CHF', 'JPY', 'CNY'];
            $filtered = array_filter(
                $rates,
                fn($k) => in_array($k, $keep, true),
                ARRAY_FILTER_USE_KEY
            );

            return $this->json([
                'base'  => $base,
                'rates' => $filtered,
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Exchange rate unavailable.'], 502);
        }
    }
}
