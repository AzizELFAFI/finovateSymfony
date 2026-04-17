<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service complet Speech-to-Speech
 * Pipeline: Audio (Whisper) → Text (Groq) → Audio (TTS)
 */
class SpeechToSpeechService
{
    private const WHISPER_URL = 'https://api.groq.com/openai/v1/audio/transcriptions';
    private const GROQ_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL = 'llama-3.3-70b-versatile';

    public function __construct(
        private HttpClientInterface $http,
        private string $groqApiKey,
        private string $projectDir,
    ) {}

    /**
     * Pipeline complet: Audio → Texte → Réponse IA (pas d'audio de sortie)
     * 
     * @param string $audioPath Chemin du fichier audio
     * @param array $chatHistory Historique de conversation
     * @param string $language Code de langue (optionnel)
     * @return array ['text' => transcription, 'reply' => réponse IA]
     */
    public function processAudioToAudio(string $audioPath, array $chatHistory = [], string $language = ''): array
    {
        // Étape 1: Transcrire l'audio en texte avec Groq Whisper
        $userText = $this->transcribeAudio($audioPath, $language);
        
        // Étape 2: Obtenir la réponse IA
        $aiReply = $this->getAiResponse($userText, $chatHistory);
        
        return [
            'text' => $userText,
            'reply' => $aiReply,
        ];
    }

    /**
     * Étape 1: Transcrire l'audio avec Groq Whisper (gratuit!)
     */
    private function transcribeAudio(string $audioPath, string $language = ''): string
    {
        if (!file_exists($audioPath)) {
            throw new \Exception("Audio file not found: $audioPath");
        }

        try {
            // Lire le fichier audio
            $fileContent = file_get_contents($audioPath);
            if ($fileContent === false) {
                throw new \Exception("Cannot read audio file: $audioPath");
            }

            // Construire le body multipart manuellement pour Groq
            $boundary = 'boundary_' . bin2hex(random_bytes(16));
            $body = '';
            
            // Ajouter le fichier
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"recording.webm\"\r\n";
            $body .= "Content-Type: audio/webm\r\n\r\n";
            $body .= $fileContent . "\r\n";
            
            // Ajouter le modèle
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"model\"\r\n\r\n";
            $body .= "whisper-large-v3-turbo\r\n";
            
            // Ajouter le format de réponse
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"response_format\"\r\n\r\n";
            $body .= "json\r\n";
            
            // Ajouter la langue si fournie
            if ($language) {
                $body .= "--{$boundary}\r\n";
                $body .= "Content-Disposition: form-data; name=\"language\"\r\n\r\n";
                $body .= $language . "\r\n";
            }
            
            // Fermer le boundary
            $body .= "--{$boundary}--\r\n";

            $response = $this->http->request('POST', self::WHISPER_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => "multipart/form-data; boundary={$boundary}",
                ],
                'body' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                $content = $response->getContent(false);
                throw new \Exception("HTTP {$statusCode}: {$content}");
            }

            $data = $response->toArray();
            if (isset($data['error'])) {
                throw new \Exception("API Error: " . json_encode($data['error']));
            }
            
            return $data['text'] ?? '';
        } catch (\Throwable $e) {
            // Log l'erreur complète pour debug
            $errorMsg = $e->getMessage();
            throw new \Exception('Groq Whisper transcription failed: ' . $errorMsg);
        }
    }

    /**
     * Étape 2: Obtenir la réponse IA avec Groq
     */
    private function getAiResponse(string $userMessage, array $chatHistory = []): string
    {
        $system = 'You are a helpful financial forum assistant for FINOVATE. Answer in the same language the user writes in. Be concise and helpful. Keep responses brief (2-3 sentences max) for voice interaction.';

        $messages = [['role' => 'system', 'content' => $system]];
        
        // Ajouter l'historique
        foreach ($chatHistory as $h) {
            $messages[] = ['role' => $h['role'], 'content' => $h['content']];
        }
        
        // Ajouter le message utilisateur
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        try {
            $response = $this->http->request('POST', self::GROQ_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => self::MODEL,
                    'temperature' => 0.7,
                    'messages' => $messages,
                ],
            ]);

            $data = $response->toArray();
            return trim($data['choices'][0]['message']['content'] ?? '');
        } catch (\Throwable $e) {
            throw new \Exception('Groq response failed: ' . $e->getMessage());
        }
    }


}