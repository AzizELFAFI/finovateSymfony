<?php

namespace App\Service;

use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class FaceApiClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private string $baseUrl
    ) {
        $this->baseUrl = rtrim($this->baseUrl, '/');
    }

    /**
     * @return array{embedding: array<int,float>, embedding_dim: int}
     */
    public function enroll(string $userKey, string $imageBytes): array
    {
        error_log("FaceApiClient::enroll - BaseURL: " . $this->baseUrl);
        $formData = new FormDataPart([
            'user_key' => $userKey,
            'image' => new DataPart($imageBytes, 'face.jpg', 'image/jpeg'),
        ]);

        try {
            $response = $this->httpClient->request('POST', $this->baseUrl . '/enroll', [
                'headers' => array_merge([
                    'Accept' => 'application/json',
                ], $formData->getPreparedHeaders()->toArray()),
                'body' => $formData->bodyToIterable(),
            ]);

            $status = $response->getStatusCode();
            $data = $response->toArray(false);
            error_log("FaceApiClient::enroll - Response Status: " . $status);
        } catch (\Throwable $e) {
            error_log("FaceApiClient::enroll - Exception: " . $e->getMessage());
            throw $e;
        }

        if ($status < 200 || $status >= 300) {
            $detail = is_array($data) && isset($data['detail']) ? (string) $data['detail'] : 'Face API enroll failed.';
            throw new \RuntimeException($detail);
        }

        $embedding = $data['embedding'] ?? null;
        $dim = $data['embedding_dim'] ?? null;

        if (!is_array($embedding) || !is_int($dim)) {
            throw new \RuntimeException('Face API returned invalid enroll response.');
        }

        return [
            'embedding' => $embedding,
            'embedding_dim' => $dim,
        ];
    }

    public function verify(string $imageBytes, string $embeddingJson, float $threshold = 0.35): array
    {
        $formData = new FormDataPart([
            'threshold' => (string) $threshold,
            'embedding_json' => $embeddingJson,
            'image' => new DataPart($imageBytes, 'face.jpg', 'image/jpeg'),
        ]);

        $response = $this->httpClient->request('POST', $this->baseUrl . '/verify', [
            'headers' => array_merge([
                'Accept' => 'application/json',
            ], $formData->getPreparedHeaders()->toArray()),
            'body' => $formData->bodyToIterable(),
        ]);

        $status = $response->getStatusCode();
        $data = $response->toArray(false);

        if ($status < 200 || $status >= 300) {
            $detail = is_array($data) && isset($data['detail']) ? (string) $data['detail'] : 'Face API verify failed.';
            throw new \RuntimeException($detail);
        }

        return is_array($data) ? $data : [];
    }
}
