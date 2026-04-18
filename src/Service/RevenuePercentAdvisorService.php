<?php

namespace App\Service;

/**
 * Fourchette de % de revenu (règles métier) + phrase explicative via IA (optional).
 */
final class RevenuePercentAdvisorService
{
    public function __construct(
        private readonly InvestmentAiService $investmentAiService,
    ) {
    }

    /**
     * @return array{min: float, max: float, label: string, ratio: float}
     */
    public function suggestRange(float $amount, float $projectGoal): array
    {
        $goal = max(0.0001, $projectGoal);
        $ratio = $amount / $goal;

        if ($ratio <= 0.02) {
            return ['min' => 2.0, 'max' => 7.0, 'label' => 'Participation minoritaire au regard de l’objectif global', 'ratio' => $ratio];
        }
        if ($ratio <= 0.08) {
            return ['min' => 5.0, 'max' => 12.0, 'label' => 'Participation modérée — équilibre courant participation / exposition', 'ratio' => $ratio];
        }
        if ($ratio <= 0.2) {
            return ['min' => 8.0, 'max' => 18.0, 'label' => 'Participation significative — pondération forte dans le financement', 'ratio' => $ratio];
        }
        if ($ratio <= 0.5) {
            return ['min' => 12.0, 'max' => 28.0, 'label' => 'Majorité ou quasi-totalité du financement prévu ; sensibilité accrue', 'ratio' => $ratio];
        }

        return ['min' => 15.0, 'max' => 40.0, 'label' => 'Très forte exposition financière au projet ; cadre à valider avec le porteur', 'ratio' => $ratio];
    }

    /**
     * Une phrase explicative (LLM) ; chaîne vide si pas de clé API.
     */
    public function explainSuggestion(
        float $amount,
        float $projectGoal,
        float $min,
        float $max,
        string $ruleLabel,
    ): string {
        return $this->investmentAiService->explainRevenuePercentRange(
            $amount,
            $projectGoal,
            $min,
            $max,
            $ruleLabel
        );
    }
}
