<?php

namespace App\Service;

use App\Entity\Ad;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Real AI-powered Ad Recommendation Service
 * 
 * Uses Groq API (LLaMA models) to analyze user profiles and recommend ads
 */
class AIRecommendationService
{
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL = 'llama-3.3-70b-versatile';

    public function __construct(
        private EntityManagerInterface $em,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $groqApiKey
    ) {}

    /**
     * Get AI-powered ad recommendations for a user
     */
    public function getRecommendedAds(User $user, int $limit = 5): array
    {
        // Get all active ads
        $ads = $this->em->getRepository(Ad::class)->findAll();
        
        if (empty($ads)) {
            return [];
        }

        // Build user profile for AI
        $userProfile = $this->buildUserProfile($user);
        $adsData = $this->buildAdsData($ads);

        // Call AI API for recommendations
        $aiRecommendations = $this->callAI($userProfile, $adsData, $limit);

        // Map AI recommendations to actual ads
        return $this->mapRecommendations($aiRecommendations, $ads, $user);
    }

    /**
     * Build user profile data for AI analysis
     */
    private function buildUserProfile(User $user): array
    {
        $age = $this->calculateAge($user->getBirthdate());
        
        // Get user's click history
        $clickHistory = $this->em->getRepository(\App\Entity\UserAdClick::class)->findBy(
            ['user' => $user],
            ['clickedAt' => 'DESC'],
            20
        );

        $clickedAdTitles = [];
        foreach ($clickHistory as $click) {
            if ($click->getAd()) {
                $clickedAdTitles[] = $click->getAd()->getTitle();
            }
        }

        return [
            'age' => $age,
            'gender' => $user->getGender() ?? 'non spécifié',
            'points' => $user->getPoints(),
            'clicked_ads' => $clickedAdTitles,
            'total_clicks' => count($clickHistory),
        ];
    }

    /**
     * Build ads data for AI analysis
     */
    private function buildAdsData(array $ads): array
    {
        $adsData = [];
        foreach ($ads as $ad) {
            $adsData[] = [
                'id' => $ad->getId(),
                'title' => $ad->getTitle(),
                'reward_points' => $ad->getRewardPoints(),
                'duration' => $ad->getDuration(),
            ];
        }
        return $adsData;
    }

    /**
     * Call OpenAI API for recommendations
     */
    private function callAI(array $userProfile, array $adsData, int $limit): array
    {
        $prompt = $this->buildPrompt($userProfile, $adsData, $limit);

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => self::MODEL,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Tu es un expert en marketing et recommandation publicitaire. Tu analyses les profils utilisateurs pour recommander les annonces les plus pertinentes. Réponds uniquement en JSON.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 1000,
                ],
                'timeout' => 30,
            ]);

            $data = $response->toArray();
            $content = $data['choices'][0]['message']['content'] ?? '';

            // Parse AI response
            return $this->parseAIResponse($content);

        } catch (\Throwable $e) {
            $this->logger->error('AI API call failed: ' . $e->getMessage());
            
            // Fallback to simple algorithm if AI fails
            return $this->fallbackRecommendation($adsData, $userProfile, $limit);
        }
    }

    /**
     * Build the prompt for AI
     */
    private function buildPrompt(array $userProfile, array $adsData, int $limit): string
    {
        $adsJson = json_encode($adsData, JSON_PRETTY_PRINT);
        $profileJson = json_encode($userProfile, JSON_PRETTY_PRINT);

        return <<<PROMPT
Analyse ce profil utilisateur et recommande les {$limit} meilleures annonces parmi celles disponibles.

PROFIL UTILISATEUR:
{$profileJson}

ANNONCES DISPONIBLES:
{$adsJson}

INSTRUCTIONS:
1. Analyse l'âge, les intérêts (basés sur les annonces cliquées), et le profil
2. Recommande les {$limit} annonces les plus pertinentes
3. Pour chaque recommandation, donne un score de pertinence (0-100) et une raison courte

RÉPONSE ATTENDUE (JSON uniquement):
{
    "recommendations": [
        {
            "ad_id": <id>,
            "score": <0-100>,
            "reason": "<raison courte en français>"
        }
    ],
    "analysis": {
        "user_interests": ["intérêt1", "intérêt2"],
        "target_age_group": "<tranche d'âge>",
        "recommendation_strategy": "<stratégie utilisée>"
    }
}
PROMPT;
    }

    /**
     * Parse AI response to extract recommendations
     */
    private function parseAIResponse(string $content): array
    {
        // Extract JSON from response
        preg_match('/\{[\s\S]*\}/m', $content, $matches);
        
        if (!$matches) {
            return [];
        }

        $data = json_decode($matches[0], true);
        
        if (!isset($data['recommendations']) || !is_array($data['recommendations'])) {
            return [];
        }

        return [
            'recommendations' => $data['recommendations'],
            'analysis' => $data['analysis'] ?? [],
        ];
    }

    /**
     * Map AI recommendations to actual Ad objects
     */
    private function mapRecommendations(array $aiResponse, array $ads, User $user): array
    {
        $adsById = [];
        foreach ($ads as $ad) {
            $adsById[$ad->getId()] = $ad;
        }

        $recommendations = [];
        foreach ($aiResponse['recommendations'] ?? [] as $rec) {
            $adId = $rec['ad_id'] ?? null;
            if ($adId && isset($adsById[$adId])) {
                // Skip ads user already clicked recently
                if ($this->hasRecentlyClicked($user, $adsById[$adId])) {
                    continue;
                }

                $recommendations[] = [
                    'ad' => $adsById[$adId],
                    'score' => $rec['score'] ?? 50,
                    'reason' => $rec['reason'] ?? 'Recommandé pour vous',
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Fallback recommendation if AI API fails
     */
    private function fallbackRecommendation(array $adsData, array $userProfile, int $limit): array
    {
        // Simple age-based fallback
        $age = $userProfile['age'];
        $recommendations = [];

        // Sort by reward points as fallback
        usort($adsData, function ($a, $b) {
            return $b['reward_points'] <=> $a['reward_points'];
        });

        foreach (array_slice($adsData, 0, $limit) as $ad) {
            $recommendations[] = [
                'ad_id' => $ad['id'],
                'score' => 50,
                'reason' => 'Annonce populaire',
            ];
        }

        return ['recommendations' => $recommendations, 'analysis' => ['fallback' => true]];
    }

    /**
     * Calculate user age from birthdate
     */
    private function calculateAge(\DateTimeInterface $birthdate): int
    {
        $now = new \DateTime();
        $diff = $now->diff($birthdate);
        return $diff->y;
    }

    /**
     * Check if user has recently clicked on this ad
     */
    private function hasRecentlyClicked(User $user, Ad $ad): bool
    {
        $recentClick = $this->em->getRepository(\App\Entity\UserAdClick::class)->findOneBy([
            'user' => $user,
            'ad' => $ad,
        ]);

        if (!$recentClick) {
            return false;
        }

        $clickDate = $recentClick->getClickedAt();
        $sevenDaysAgo = new \DateTime('-7 days');

        return $clickDate > $sevenDaysAgo;
    }

    /**
     * Get AI analysis for user (for display)
     */
    public function getUserAnalysis(User $user): array
    {
        $userProfile = $this->buildUserProfile($user);
        $ads = $this->em->getRepository(Ad::class)->findAll();
        $adsData = $this->buildAdsData($ads);

        $aiResponse = $this->callAI($userProfile, $adsData, 3);

        return [
            'age' => $userProfile['age'],
            'interests' => $aiResponse['analysis']['user_interests'] ?? [],
            'strategy' => $aiResponse['analysis']['recommendation_strategy'] ?? 'Analyse IA',
        ];
    }

    /**
     * Auto-categorize and target an ad using AI
     */
    public function autoTargetAd(Ad $ad): array
    {
        $prompt = <<<PROMPT
Analyse cette annonce publicitaire et détermine le ciblage optimal.

TITRE: {$ad->getTitle()}
POINTS: {$ad->getRewardPoints()}
DURÉE: {$ad->getDuration()} secondes

Détermine:
1. La catégorie (finance, tech, lifestyle, shopping, travel)
2. La tranche d'âge cible (min, max)
3. Les centres d'intérêt associés
4. Un score de priorité (0.1-10)

Réponds en JSON:
{
    "category": "<catégorie>",
    "age_min": <18-70>,
    "age_max": <18-70>,
    "interests": ["intérêt1", "intérêt2"],
    "priority": <0.1-10>,
    "target_gender": "<all/male/female>"
}
PROMPT;

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => self::MODEL,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Tu es un expert en marketing digital. Réponds uniquement en JSON.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.5,
                    'max_tokens' => 500,
                ],
                'timeout' => 20,
            ]);

            $data = $response->toArray();
            $content = $data['choices'][0]['message']['content'] ?? '';

            preg_match('/\{[\s\S]*\}/m', $content, $matches);
            
            if ($matches) {
                return json_decode($matches[0], true);
            }

        } catch (\Throwable $e) {
            $this->logger->error('AI auto-targeting failed: ' . $e->getMessage());
        }

        // Fallback
        return [
            'category' => 'finance',
            'age_min' => 18,
            'age_max' => 65,
            'interests' => ['finance'],
            'priority' => 1.0,
            'target_gender' => 'all',
        ];
    }
}
