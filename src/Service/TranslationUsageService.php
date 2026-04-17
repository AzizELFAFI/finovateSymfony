<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service pour monitorer l'utilisation de l'API Langbly
 */
class TranslationUsageService
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {}

    /**
     * Log une traduction et compte les caractères
     */
    public function logTranslation(string $text, string $targetLang, bool $success = true): void
    {
        $characterCount = mb_strlen($text, 'UTF-8');
        
        // Log dans les fichiers de log
        $this->logger->info('Translation API Usage', [
            'service' => 'langbly',
            'characters' => $characterCount,
            'target_language' => $targetLang,
            'success' => $success,
            'date' => date('Y-m-d H:i:s'),
        ]);

        // Optionnel : Sauvegarder en base de données
        try {
            $this->em->getConnection()->executeStatement(
                'INSERT INTO translation_usage (characters_used, target_language, success, created_at) VALUES (?, ?, ?, NOW())',
                [$characterCount, $targetLang, $success ? 1 : 0]
            );
        } catch (\Throwable $e) {
            // Ignore si la table n'existe pas
        }
    }

    /**
     * Obtenir les statistiques d'utilisation du mois en cours
     */
    public function getMonthlyUsage(): array
    {
        $startOfMonth = date('Y-m-01 00:00:00');
        $endOfMonth = date('Y-m-t 23:59:59');

        try {
            $result = $this->em->getConnection()->fetchAssociative(
                'SELECT 
                    COUNT(*) as total_requests,
                    SUM(characters_used) as total_characters,
                    SUM(CASE WHEN success = 1 THEN characters_used ELSE 0 END) as successful_characters,
                    AVG(characters_used) as avg_characters_per_request
                FROM translation_usage 
                WHERE created_at BETWEEN ? AND ?',
                [$startOfMonth, $endOfMonth]
            );

            return [
                'total_requests' => (int) ($result['total_requests'] ?? 0),
                'total_characters' => (int) ($result['total_characters'] ?? 0),
                'successful_characters' => (int) ($result['successful_characters'] ?? 0),
                'avg_characters_per_request' => round((float) ($result['avg_characters_per_request'] ?? 0), 2),
                'free_limit' => 500000, // 500K caractères gratuits
                'usage_percentage' => round(((int) ($result['successful_characters'] ?? 0) / 500000) * 100, 2),
            ];
        } catch (\Throwable $e) {
            return [
                'total_requests' => 0,
                'total_characters' => 0,
                'successful_characters' => 0,
                'avg_characters_per_request' => 0,
                'free_limit' => 500000,
                'usage_percentage' => 0,
                'error' => 'Table translation_usage not found',
            ];
        }
    }

    /**
     * Vérifier si on approche de la limite mensuelle
     */
    public function isApproachingLimit(float $threshold = 80.0): bool
    {
        $usage = $this->getMonthlyUsage();
        return $usage['usage_percentage'] >= $threshold;
    }

    /**
     * Obtenir un rapport détaillé par langue
     */
    public function getUsageByLanguage(): array
    {
        $startOfMonth = date('Y-m-01 00:00:00');
        $endOfMonth = date('Y-m-t 23:59:59');

        try {
            $results = $this->em->getConnection()->fetchAllAssociative(
                'SELECT 
                    target_language,
                    COUNT(*) as requests,
                    SUM(characters_used) as characters,
                    AVG(characters_used) as avg_characters
                FROM translation_usage 
                WHERE created_at BETWEEN ? AND ? AND success = 1
                GROUP BY target_language
                ORDER BY characters DESC',
                [$startOfMonth, $endOfMonth]
            );

            return array_map(function($row) {
                return [
                    'language' => $row['target_language'],
                    'requests' => (int) $row['requests'],
                    'characters' => (int) $row['characters'],
                    'avg_characters' => round((float) $row['avg_characters'], 2),
                ];
            }, $results);
        } catch (\Throwable $e) {
            return [];
        }
    }
}