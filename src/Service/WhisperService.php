<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service pour transcrire l'audio via OpenAI Whisper API
 */
class WhisperService
{
    private const WHISPER_URL = 'https://api.openai.com/v1/audio/transcriptions';

    public function __construct(
        private HttpClientInterface $http,
        private string $openaiApiKey,
    ) {}

    /**
     * Transcrire un fichier audio en texte
     * 
     * @param string $audioPath Chemin du fichier audio (mp3, wav, m4a, ogg, flac)
     * @param string $language Code de langue ISO-639-1 (optionnel, ex: 'fr', 'en', 'ar')
     * @return string Texte transcrit
     */
    public function transcribe(string $audioPath, string $language = ''): string
    {
        if (!file_exists($audioPath)) {
            throw new \Exception("Audio file not found: $audioPath");
        }

        try {
            $response = $this->http->request('POST', self::WHISPER_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                ],
                'extra' => [
                    'multipart' => [
                        [
                            'name' => 'file',
                            'filename' => basename($audioPath),
                            'contents' => fopen($audioPath, 'r'),
                        ],
                        [
                            'name' => 'model',
                            'contents' => 'whisper-1',
                        ],
                        [
                            'name' => 'response_format',
                            'contents' => 'json',
                        ],
                        // Optionnel: spécifier la langue
                        ...$language ? [
                            [
                                'name' => 'language',
                                'contents' => $language,
                            ]
                        ] : [],
                    ],
                ],
            ]);

            $data = $response->toArray();
            return $data['text'] ?? '';
        } catch (\Throwable $e) {
            throw new \Exception('Whisper transcription failed: ' . $e->getMessage());
        }
    }

    /**
     * Transcrire un fichier audio avec détection de langue
     */
    public function transcribeWithDetection(string $audioPath): array
    {
        if (!file_exists($audioPath)) {
            throw new \Exception("Audio file not found: $audioPath");
        }

        try {
            $response = $this->http->request('POST', self::WHISPER_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                ],
                'extra' => [
                    'multipart' => [
                        [
                            'name' => 'file',
                            'filename' => basename($audioPath),
                            'contents' => fopen($audioPath, 'r'),
                        ],
                        [
                            'name' => 'model',
                            'contents' => 'whisper-1',
                        ],
                        [
                            'name' => 'response_format',
                            'contents' => 'verbose_json',
                        ],
                    ],
                ],
            ]);

            $data = $response->toArray();
            return [
                'text' => $data['text'] ?? '',
                'language' => $data['language'] ?? 'unknown',
                'duration' => $data['duration'] ?? 0,
            ];
        } catch (\Throwable $e) {
            throw new \Exception('Whisper transcription failed: ' . $e->getMessage());
        }
    }
}