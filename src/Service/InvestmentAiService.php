<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Groq (OpenAI-compatible) ou OpenAI pour résumés investisseur.
 */
final class InvestmentAiService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $groqApiKey = '',
        private readonly string $openaiApiKey = '',
    ) {
    }

    /**
     * @return list<string> 3 puces courtes
     */
    public function projectRiskBullets(
        string $title,
        string $description,
        float $goalTnd,
        float $percentFunded,
    ): array {
        $prompt = sprintf(
            "Projet de financement participatif.\nTitre: %s\nDescription: %s\nObjectif: %.2f TND\nAvancement collecte: %.1f%% du objectif.\n\n".
            "Liste exactement 3 puces courtes (risques ou points clés pour un investisseur), en français. ".
            "Une info par ligne, préfixe '- '. Pas d'introduction.",
            $title,
            mb_substr(strip_tags($description), 0, 1200),
            $goalTnd,
            $percentFunded
        );

        $text = $this->chatCompletion(
            'Tu es un analyste finance prudent. Réponses factuelles, sans promesse de gain.',
            $prompt,
            400
        );

        if ($text === '') {
            return [
                'Service de synthèse IA indisponible (clé Groq/OpenAI ou réseau). À défaut : lisez la description du projet et les éléments vérifiables.',
                'Contrôlez le niveau de collecte par rapport à l’objectif et la fourchette de votre participation.',
                'Investissez uniquement des montants compatibles avec votre situation ; aucun rendement n’est garanti.',
            ];
        }

        return $this->parseBullets($text);
    }

    public function explainRevenuePercentRange(
        float $amount,
        float $goal,
        float $minPct,
        float $maxPct,
        string $ruleLabel,
    ): string {
        $prompt = sprintf(
            "Contexte: demande d'investissement %.2f TND sur un projet d'objectif %.2f TND. ".
            "Fourchette de pourcentage de revenu suggérée: %.1f%% à %.1f%% (%s).\n".
            "Écris UNE phrase en français (max 220 caractères) pour expliquer à l'investisseur pourquoi cette fourchette a du sens, sans jargon légal.",
            $amount,
            $goal,
            $minPct,
            $maxPct,
            $ruleLabel
        );

        $out = $this->chatCompletion(
            'Tu es un conseiller en finance participative, ton neutre et pédagogique.',
            $prompt,
            200
        );

        return $out !== '' ? trim($out) : '';
    }

    private function chatCompletion(string $system, string $user, int $maxTokens): string
    {
        $groq = trim($this->groqApiKey);
        $openai = trim($this->openaiApiKey);

        if ($groq !== '') {
            $text = $this->postOpenAiCompatible(
                'https://api.groq.com/openai/v1/chat/completions',
                $groq,
                'llama-3.1-8b-instant',
                $system,
                $user,
                $maxTokens
            );
            if ($text !== '') {
                return $text;
            }
        }

        if ($openai !== '') {
            return $this->postOpenAiCompatible(
                'https://api.openai.com/v1/chat/completions',
                $openai,
                'gpt-4o-mini',
                $system,
                $user,
                $maxTokens
            );
        }

        return '';
    }

    private function postOpenAiCompatible(
        string $url,
        string $bearer,
        string $model,
        string $system,
        string $user,
        int $maxTokens,
    ): string {
        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $bearer,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                    'temperature' => 0.35,
                    'max_tokens' => $maxTokens,
                ],
                'timeout' => 25,
            ]);
            $data = $response->toArray(false);
            $content = $data['choices'][0]['message']['content'] ?? '';

            return is_string($content) ? $content : '';
        } catch (\Throwable $e) {
            $this->logger->warning('InvestmentAiService: ' . $e->getMessage());

            return '';
        }
    }

    /**
     * @return list<string>
     */
    private function parseBullets(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line, " \t\n\r\0\x0B-•*");
            if ($line !== '' && count($out) < 3) {
                $out[] = $line;
            }
        }
        while (count($out) < 3) {
            $out[] = '—';
        }

        return array_slice($out, 0, 3);
    }
}
