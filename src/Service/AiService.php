<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiService
{
    private const GROQ_URL   = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL      = 'llama-3.3-70b-versatile';
    private const OLLAMA_URL = 'http://localhost:11434/api/chat';
    private const OLLAMA_MODEL = 'gemma3:1b';
    private const LANGBLY_URL = 'https://api.langbly.com/language/translate/v2';

    public function __construct(
        private HttpClientInterface $http,
        private string $groqApiKey,
        private string $langblyApiKey,
        private ?TranslationUsageService $usageService = null,
    ) {}

    // ── Core call ─────────────────────────────────────────────────────────────

    private function call(string $system, string $user, float $temp = 0.7): string
    {
        $response = $this->http->request('POST', self::GROQ_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->groqApiKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model'       => self::MODEL,
                'temperature' => $temp,
                'messages'    => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user',   'content' => $user],
                ],
            ],
        ]);

        $data = $response->toArray();
        return trim($data['choices'][0]['message']['content'] ?? '');
    }
    // ----------EXTRAIRE SIGNUP

    public function extractSignupField(string $field, string $transcript): string
    {
        $field = trim($field);
        $transcript = trim($transcript);

        if ($field === '' || $transcript === '') {
            return '';
        }

        $allowed = ['nom', 'prenom', 'email', 'date_naissance', 'cin', 'telephone'];
        if (!in_array($field, $allowed, true)) {
            return '';
        }

        $constraints = match ($field) {
            'nom', 'prenom' => 'Return a person name in French. Keep letters, spaces, hyphen, apostrophe. Minimum 3 characters.',
            'email' => 'Return a valid email. Convert French spoken tokens: "arobase" -> "@", "point" -> ".". Remove spaces. Lowercase.',
            'date_naissance' => 'Return a date in ISO format YYYY-MM-DD. Understand French dates (e.g. "12 janvier 2001", "douze janvier deux mille un").',
            'cin' => 'Return exactly 8 digits. Remove spaces.',
            'telephone' => 'Return exactly 8 digits. Remove spaces.',
            default => 'Return a value.',
        };

        $result = $this->call(
            'You are an information extraction engine. Reply ONLY with valid JSON: {"value":"..."}. No markdown. No extra keys. If unsure, return {"value":""}.',
            "Field: {$field}\nConstraint: {$constraints}\nTranscript: {$transcript}",
            0.2
        );

        $result = preg_replace('/^```(?:json)?\s*/i', '', trim($result));
        $result = preg_replace('/\s*```$/', '', $result);

        $decoded = json_decode($result, true);
        if (!is_array($decoded)) {
            return '';
        }

        $value = $decoded['value'] ?? '';
        if (!is_string($value)) {
            return '';
        }

        return trim($value);
    }

    /** Extract all signup fields from a single phrase */
    public function extractAllSignupFields(string $transcript): array
    {
        $transcript = trim($transcript);
        if ($transcript === '') {
            return [];
        }

        $result = $this->call(
            'You are an information extraction engine. Extract signup fields from the user spoken phrase in French. Reply ONLY with valid JSON object with these keys (use empty string if not found): {"nom":"...","prenom":"...","email":"...","date_naissance":"YYYY-MM-DD","cin":"8 digits","telephone":"8 digits"}. No markdown. No extra keys. Convert spoken tokens: "arobase" -> "@", "point" -> ".". Understand French dates like "12 janvier 2001". Remove spaces from CIN/telephone.',
            $transcript,
            0.2
        );

        $result = preg_replace('/^```(?:json)?\s*/i', '', trim($result));
        $result = preg_replace('/\s*```$/', '', $result);

        $decoded = json_decode($result, true);
        if (!is_array($decoded)) {
            return [];
        }

        // Normalize each field
        $fields = ['nom', 'prenom', 'email', 'date_naissance', 'cin', 'telephone'];
        $out = [];
        foreach ($fields as $f) {
            $val = $decoded[$f] ?? '';
            $out[$f] = is_string($val) ? trim($val) : '';
        }

        return $out;
    }

    // -------------------------------------------------------------------------

    /** General chat assistant */
    public function chat(string $message, array $history = []): string
    {
        $system = 'You are a helpful financial forum assistant for FINOVATE. Answer in the same language the user writes in. Be concise and helpful.';

        $messages = [['role' => 'system', 'content' => $system]];
        foreach ($history as $h) {
            $messages[] = ['role' => $h['role'], 'content' => $h['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        $response = $this->http->request('POST', self::GROQ_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->groqApiKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => ['model' => self::MODEL, 'temperature' => 0.7, 'messages' => $messages],
        ]);

        $data = $response->toArray();
        return trim($data['choices'][0]['message']['content'] ?? '');
    }

    /** Generate a forum post */
    public function generatePost(string $theme, string $tone, string $length): array
    {
        $wordCount = match($length) {
            'short'  => '80-120',
            'long'   => '300-400',
            default  => '150-200',
        };

        $result = $this->call(
            'You are a professional financial content writer. Reply ONLY with valid JSON: {"title":"...","content":"..."}. No markdown, no explanation.',
            "Write a forum post about: $theme. Tone: $tone. Length: $wordCount words. Language: French.",
            0.8
        );

        // strip possible markdown code fences
        $result = preg_replace('/^```(?:json)?\s*/i', '', trim($result));
        $result = preg_replace('/\s*```$/', '', $result);

        $decoded = json_decode($result, true);
        if (!$decoded) {
            return ['title' => $theme, 'content' => $result];
        }
        return $decoded;
    }

    /** Summarize a post */
    public function summarize(string $title, string $content): string
    {
        return $this->call(
            'You are a concise summarizer. Reply in the same language as the text. Give a 2-3 sentence summary only.',
            "Title: $title\n\nContent: $content"
        );
    }

    /** Translate a post using Langbly API */
    public function translate(string $text, string $targetLang): string
    {
        // Map language names to ISO codes if needed
        $langMap = [
            'English' => 'en', 'French' => 'fr', 'Arabic' => 'ar',
            'Spanish' => 'es', 'German' => 'de', 'Italian' => 'it',
            'Portuguese' => 'pt', 'Russian' => 'ru', 'Chinese' => 'zh',
            'Japanese' => 'ja', 'Korean' => 'ko', 'Dutch' => 'nl',
        ];
        $langCode = $langMap[$targetLang] ?? strtolower(substr($targetLang, 0, 2));

        try {
            $response = $this->http->request('POST', self::LANGBLY_URL, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-API-Key'    => $this->langblyApiKey,
                ],
                'json' => [
                    'q'      => $text,
                    'target' => $langCode,
                    'format' => 'text',
                ],
            ]);
            $data = $response->toArray();
            $translation = $data['data']['translations'][0]['translatedText'] ?? $text;
            
            // Log successful translation
            $this->usageService?->logTranslation($text, $targetLang, true);
            
            return $translation;
        } catch (\Throwable $e) {
            // Log failed Langbly translation
            $this->usageService?->logTranslation($text, $targetLang, false);
            
            // Fallback to Groq if Langbly fails
            return $this->call(
                "You are a translator. Translate the following text to $targetLang. Reply with only the translated text, nothing else.",
                $text
            );
        }
    }

    /** Analyse personality from activity stats */
    public function analyzePersonality(array $stats): array
    {
        $prompt = sprintf(
            'User activity on a financial forum: %d posts, %d comments, %d votes, joined %d forums, %d shares. Upvotes given: %d, Downvotes given: %d.',
            $stats['posts'], $stats['comments'], $stats['votes'],
            $stats['forums'], $stats['shares'],
            $stats['upvotes'] ?? 0, $stats['downvotes'] ?? 0
        );

        $result = $this->call(
            'You are a personality analyst for a financial forum. Based on user activity, return ONLY valid JSON with keys: title (string, emoji + short title), description (2-3 sentences in French), traits (array of 3-5 French trait strings), sentiment (object with positive/neutral/negative as integers summing to 100). No markdown.',
            $prompt,
            0.6
        );

        $result = preg_replace('/^```(?:json)?\s*/i', '', trim($result));
        $result = preg_replace('/\s*```$/', '', $result);

        $decoded = json_decode($result, true);
        if (!$decoded) {
            return [
                'title'       => '👀 Observateur Attentif',
                'description' => 'Profil en cours d\'analyse.',
                'traits'      => ['Curieux'],
                'sentiment'   => ['positive' => 50, 'neutral' => 40, 'negative' => 10],
            ];
        }
        return $decoded;
    }

    /** Generate audience growth plan based on activity stats */
    public function generateGrowthPlan(array $stats): array
    {
        $prompt = sprintf(
            'Financial forum user stats: %d posts, %d comments, %d votes, joined %d forums, %d shares. Posts per day average: %.1f.',
            $stats['posts'], $stats['comments'], $stats['votes'],
            $stats['forums'], $stats['shares'],
            $stats['postsPerDay'] ?? 0
        );

        $result = $this->call(
            'You are a social media growth strategist for a financial forum. Based on the user\'s activity, create a personalized growth plan. Return ONLY valid JSON with keys: bestPostingTime (string, e.g. "18h-20h"), postsPerDay (int, recommended), recommendedForums (array of 3 forum topic strings in French), contentIdeas (array of 5 post topic ideas in French), weeklyGoal (string, one sentence in French), tips (array of 3 actionable tips in French). No markdown.',
            $prompt,
            0.7
        );

        $result = preg_replace('/^```(?:json)?\s*/i', '', trim($result));
        $result = preg_replace('/\s*```$/', '', $result);

        $decoded = json_decode($result, true);
        return $decoded ?? [
            'bestPostingTime'    => '18h-20h',
            'postsPerDay'        => 2,
            'recommendedForums'  => ['Crypto & Blockchain', 'Investissement', 'Finance Personnelle'],
            'contentIdeas'       => ['Analyse du marché', 'Conseils d\'investissement', 'Actualités financières', 'Tutoriels DeFi', 'Revue de portefeuille'],
            'weeklyGoal'         => 'Publiez 10 posts cette semaine pour augmenter votre visibilité.',
            'tips'               => ['Postez aux heures de pointe', 'Répondez aux commentaires rapidement', 'Partagez du contenu original'],
        ];
    }

    /** Moderate a comment — returns ['safe'=>bool, 'warning'=>string] */
    public function moderateComment(string $text): array
    {
        $result = $this->call(
            'You are a content moderator for a financial forum. Detect toxic, offensive, or inappropriate content. Reply ONLY with JSON: {"safe": true/false, "warning": "message in French if not safe, empty string if safe"}. No markdown.',
            $text,
            0.2
        );

        $result = preg_replace('/^```(?:json)?\s*/i', '', trim($result));
        $result = preg_replace('/\s*```$/', '', $result);

        $decoded = json_decode($result, true);
        return $decoded ?? ['safe' => true, 'warning' => ''];
    }

    /** Autocomplete a comment */
    public function autocomplete(string $partial): string
    {
        return $this->call(
            'You are helping a user finish their comment on a financial forum. Complete the sentence naturally in the same language. Reply with ONLY the completion (not the original text), max 20 words.',
            $partial,
            0.7
        );
    }

    /** Detect misinformation in a post */
    public function detectMisinformation(string $title, string $content): array
    {
        $result = $this->call(
            'You are a financial fact-checker. Analyze the post for unverified claims, misinformation, or misleading financial advice. Reply ONLY with JSON: {"flagged": true/false, "issues": ["issue1","issue2"], "verdict": "short verdict in French"}. No markdown.',
            "Title: $title\n\nContent: $content",
            0.3
        );

        $result = preg_replace('/^```(?:json)?\s*/i', '', trim($result));
        $result = preg_replace('/\s*```$/', '', $result);

        $decoded = json_decode($result, true);
        return $decoded ?? ['flagged' => false, 'issues' => [], 'verdict' => 'Aucun problème détecté.'];
    }

    /** Generate image via Pollinations.ai and save locally */
    public function generateImage(string $prompt, string $uploadDir): ?string
    {
        $encoded  = rawurlencode($prompt);
        $url      = 'https://image.pollinations.ai/prompt/' . $encoded . '?width=512&height=512&nologo=true';

        $response = $this->http->request('GET', $url, ['timeout' => 30]);
        $content  = $response->getContent(false);

        if (!$content || $response->getStatusCode() !== 200) return null;

        $filename = 'ai_' . uniqid() . '.jpg';
        file_put_contents($uploadDir . '/' . $filename, $content);

        return 'uploads/images/' . $filename;
    }

    /** Rewrite user text in a better, more professional way */
    public function rewriteText(string $text): string
    {
        return $this->call(
            'You are a professional financial content editor. Rewrite the following text to make it clearer, more engaging, and more professional. Keep the same language and meaning. Reply with ONLY the rewritten text, no explanation.',
            $text,
            0.7
        );
    }

    /** Detect toxic discussion and suggest cool-down message */
    public function detectToxicity(array $comments): array
    {
        $text = implode("\n", array_map(fn($c) => '- ' . $c, $comments));

        $result = $this->call(
            'You are a community manager for a financial forum. Analyze the discussion tone. Reply ONLY with JSON: {"toxic": true/false, "level": "low/medium/high", "suggestion": "a calm cool-down message in French to show users if toxic"}. No markdown.',
            $text,
            0.3
        );

        $result = preg_replace('/^```(?:json)?\s*/i', '', trim($result));
        $result = preg_replace('/\s*```$/', '', $result);

        $decoded = json_decode($result, true);
        return $decoded ?? ['toxic' => false, 'level' => 'low', 'suggestion' => ''];
    }

    /** Generate personalized financial advice based on user data */
    public function generateFinancialAdvice(array $userData): string
    {
        $prompt = sprintf(
            "Données financières de l'utilisateur:\n" .
            "- Solde actuel: %.2f TND\n" .
            "- Points de fidélité: %d\n" .
            "- Transactions envoyées aujourd'hui: %.2f TND\n" .
            "- Transactions envoyées ce mois: %.2f TND\n" .
            "- Total reçu ce mois: %.2f TND\n" .
            "- Nombre de transactions envoyées: %d\n" .
            "- Nombre de transactions reçues: %d\n" .
            "- Limite quotidienne restante: %.2f TND\n" .
            "- Nombre de goals: %d\n" .
            "- Goals en cours: %d\n" .
            "- Goals complétés: %d\n" .
            "- Montant total épargné dans goals: %.2f TND",
            $userData['solde'] ?? 0,
            $userData['points'] ?? 0,
            $userData['sent_today'] ?? 0,
            $userData['sent_this_month'] ?? 0,
            $userData['total_received'] ?? 0,
            $userData['sent_count'] ?? 0,
            $userData['received_count'] ?? 0,
            $userData['daily_remaining'] ?? 3000,
            $userData['goals_count'] ?? 0,
            $userData['goals_in_progress'] ?? 0,
            $userData['goals_completed'] ?? 0,
            $userData['goals_total_saved'] ?? 0
        );

        return $this->call(
            "Tu es un conseiller financier personnel expert de FINOVATE, une application bancaire tunisienne. Analyse les données financières de l'utilisateur et donne des conseils personnalisés en français. " .
            "Ton conseil doit être structuré avec:\n" .
            "1. Une analyse rapide de sa situation financière actuelle\n" .
            "2. 2-3 conseils concrets et actionnables pour améliorer sa gestion financière\n" .
            "3. Des suggestions sur ses goals si applicable\n" .
            "4. Une recommandation finale motivante\n\n" .
            "Sois bienveillant, pratique et utilise des émojis pour rendre le conseil engageant. Reste concis (max 300 mots).",
            $prompt,
            0.8
        );
    }
}
