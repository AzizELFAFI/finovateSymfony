<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
/**
 * Taux TND → EUR / USD via API publique (exchangerate.host), sans clé.
 */
final class InvestmentFxService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return array{EUR: float, USD: float}|null Taux : 1 TND = x EUR (idem USD)
     */
    public function fetchTndRates(): ?array
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                'https://api.exchangerate.host/latest',
                [
                    'query' => [
                        'base' => 'TND',
                        'symbols' => 'EUR,USD',
                    ],
                    'timeout' => 8,
                ]
            );
            $data = $response->toArray(false);
            if (($data['success'] ?? false) !== true) {
                return $this->fetchFrankfurterFallback();
            }
            $rates = $data['rates'] ?? [];

            return [
                'EUR' => (float) ($rates['EUR'] ?? 0),
                'USD' => (float) ($rates['USD'] ?? 0),
            ];
        } catch (\Throwable) {
            return $this->fetchFrankfurterFallback();
        }
    }

    /**
     * Frankfurter : base EUR, pas toujours TND — fallback USD comme pivot.
     */
    private function fetchFrankfurterFallback(): ?array
    {
        try {
            $r = $this->httpClient->request(
                'GET',
                'https://api.frankfurter.app/latest',
                [
                    'query' => [
                        'from' => 'USD',
                        'to' => 'EUR,TND',
                    ],
                    'timeout' => 8,
                ]
            )->toArray(false);
            $rates = $r['rates'] ?? [];
            // 1 USD = X EUR ; 1 USD = Y TND → 1 TND = X/Y EUR, 1 TND = 1/Y USD
            $usdEur = (float) ($rates['EUR'] ?? 0);
            $usdTnd = (float) ($rates['TND'] ?? 0);
            if ($usdTnd <= 0.0) {
                return null;
            }

            return [
                'EUR' => $usdEur / $usdTnd,
                'USD' => 1.0 / $usdTnd,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array{EUR: float, USD: float}|null $rates
     *
     * @return array{tnd: float, eur: float, usd: float, rate_eur: float, rate_usd: float}
     */
    public function convertFromTnd(float $amountTnd, ?array $rates): array
    {
        $rates ??= ['EUR' => 0.0, 'USD' => 0.0];
        $re = $rates['EUR'] ?? 0.0;
        $ru = $rates['USD'] ?? 0.0;

        return [
            'tnd' => $amountTnd,
            'eur' => round($amountTnd * $re, 2),
            'usd' => round($amountTnd * $ru, 2),
            'rate_eur' => $re,
            'rate_usd' => $ru,
        ];
    }
}
