<?php

namespace App\Tests\Service;

use App\Entity\Goal;
use App\Service\GoalManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le service GoalManager.
 * 
 * Règles métier testées:
 * 1. Le titre doit contenir entre 3 et 40 caractères
 * 2. La deadline doit être dans le futur
 * 3. Le montant objectif doit être supérieur à 0
 * 4. Le montant actuel doit être >= 0
 */
class GoalManagerTest extends TestCase
{
    private GoalManager $goalManager;

    protected function setUp(): void
    {
        $this->goalManager = new GoalManager();
    }

    /**
     * Crée un objectif avec les propriétés spécifiées.
     */
    private function createGoal(string $title, ?\DateTimeInterface $deadline = null, float $targetAmount = 1000, float $currentAmount = 0): Goal
    {
        $goal = new Goal();
        $goal->setId(1);
        $goal->setId_user(1);
        $goal->setTitle($title);
        $goal->setDeadline($deadline ?? new \DateTime('+30 days'));
        $goal->setStatus('IN_PROGRESS');
        $goal->setCreated_at(new \DateTime());
        $goal->setTarget_amount((string) $targetAmount);
        $goal->setCurrent_amount((string) $currentAmount);
        
        return $goal;
    }

    /**
     * Test: Objectif valide avec toutes les règles respectées.
     */
    public function testValidGoal(): void
    {
        $goal = $this->createGoal('Vacances été');
        
        $this->assertTrue($this->goalManager->validate($goal));
    }

    /**
     * Test: Titre trop court (moins de 3 caractères).
     */
    public function testGoalWithShortTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre doit contenir entre 3 et 40 caractères.');
        
        $goal = $this->createGoal('AB');
        
        $this->goalManager->validate($goal);
    }

    /**
     * Test: Titre trop long (plus de 40 caractères).
     */
    public function testGoalWithLongTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre doit contenir entre 3 et 40 caractères.');
        
        $longTitle = str_repeat('A', 41);
        $goal = $this->createGoal($longTitle);
        
        $this->goalManager->validate($goal);
    }

    /**
     * Test: Titre avec exactement 3 caractères (limite).
     */
    public function testGoalWithExactlyThreeCharactersTitle(): void
    {
        $goal = $this->createGoal('ABC');
        
        $this->assertTrue($this->goalManager->validate($goal));
    }

    /**
     * Test: Titre avec exactement 40 caractères (limite).
     */
    public function testGoalWithExactlyFortyCharactersTitle(): void
    {
        $title = str_repeat('A', 40);
        $goal = $this->createGoal($title);
        
        $this->assertTrue($this->goalManager->validate($goal));
    }

    /**
     * Test: Deadline dans le passé (invalide).
     */
    public function testGoalWithPastDeadline(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La deadline doit être dans le futur.');
        
        $goal = $this->createGoal('Test goal', new \DateTime('-1 day'));
        
        $this->goalManager->validate($goal);
    }

    /**
     * Test: Deadline aujourd'hui (invalide).
     */
    public function testGoalWithTodayDeadline(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La deadline doit être dans le futur.');
        
        $goal = $this->createGoal('Test goal', new \DateTime('today'));
        
        $this->goalManager->validate($goal);
    }

    /**
     * Test: Montant objectif égal à 0 (invalide).
     */
    public function testGoalWithZeroTargetAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le montant objectif doit être supérieur à 0.');
        
        $goal = $this->createGoal('Test goal', null, 0);
        
        $this->goalManager->validate($goal);
    }

    /**
     * Test: Montant objectif négatif (invalide).
     */
    public function testGoalWithNegativeTargetAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le montant objectif doit être supérieur à 0.');
        
        $goal = $this->createGoal('Test goal', null, -100);
        
        $this->goalManager->validate($goal);
    }

    /**
     * Test: Montant actuel négatif (invalide).
     */
    public function testGoalWithNegativeCurrentAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le montant actuel ne peut pas être négatif.');
        
        $goal = $this->createGoal('Test goal', null, 1000, -50);
        
        $this->goalManager->validate($goal);
    }

    /**
     * Test: Montant actuel égal à 0 (valide).
     */
    public function testGoalWithZeroCurrentAmount(): void
    {
        $goal = $this->createGoal('Test goal', null, 1000, 0);
        
        $this->assertTrue($this->goalManager->validate($goal));
    }

    /**
     * Test: Méthode isTitleValid avec titre valide.
     */
    public function testIsTitleValidWithValidTitle(): void
    {
        $goal = $this->createGoal('Vacances');
        
        $this->assertTrue($this->goalManager->isTitleValid($goal));
    }

    /**
     * Test: Méthode isTitleValid avec titre invalide.
     */
    public function testIsTitleValidWithInvalidTitle(): void
    {
        $goal = $this->createGoal('AB');
        
        $this->assertFalse($this->goalManager->isTitleValid($goal));
    }

    /**
     * Test: Méthode isDeadlineInFuture avec deadline future.
     */
    public function testIsDeadlineInFutureWithFutureDate(): void
    {
        $goal = $this->createGoal('Test', new \DateTime('+10 days'));
        
        $this->assertTrue($this->goalManager->isDeadlineInFuture($goal));
    }

    /**
     * Test: Méthode isDeadlineInFuture avec deadline passée.
     */
    public function testIsDeadlineInFutureWithPastDate(): void
    {
        $goal = $this->createGoal('Test', new \DateTime('-1 day'));
        
        $this->assertFalse($this->goalManager->isDeadlineInFuture($goal));
    }

    /**
     * Test: Méthode isTargetAmountValid avec montant valide.
     */
    public function testIsTargetAmountValidWithValidAmount(): void
    {
        $goal = $this->createGoal('Test', null, 500);
        
        $this->assertTrue($this->goalManager->isTargetAmountValid($goal));
    }

    /**
     * Test: Méthode isTargetAmountValid avec montant invalide.
     */
    public function testIsTargetAmountValidWithInvalidAmount(): void
    {
        $goal = $this->createGoal('Test', null, 0);
        
        $this->assertFalse($this->goalManager->isTargetAmountValid($goal));
    }

    /**
     * Test: Méthode isCurrentAmountValid avec montant valide.
     */
    public function testIsCurrentAmountValidWithValidAmount(): void
    {
        $goal = $this->createGoal('Test', null, 1000, 500);
        
        $this->assertTrue($this->goalManager->isCurrentAmountValid($goal));
    }

    /**
     * Test: Méthode isCurrentAmountValid avec montant négatif.
     */
    public function testIsCurrentAmountValidWithNegativeAmount(): void
    {
        $goal = $this->createGoal('Test', null, 1000, -10);
        
        $this->assertFalse($this->goalManager->isCurrentAmountValid($goal));
    }

    /**
     * Test: Calcul du pourcentage de progression.
     */
    public function testGetProgressPercentage(): void
    {
        $goal = $this->createGoal('Test', null, 1000, 250);
        
        $this->assertEquals(25.0, $this->goalManager->getProgressPercentage($goal));
    }

    /**
     * Test: Calcul du pourcentage de progression à 50%.
     */
    public function testGetProgressPercentageFiftyPercent(): void
    {
        $goal = $this->createGoal('Test', null, 1000, 500);
        
        $this->assertEquals(50.0, $this->goalManager->getProgressPercentage($goal));
    }

    /**
     * Test: Calcul du pourcentage de progression à 100%.
     */
    public function testGetProgressPercentageHundredPercent(): void
    {
        $goal = $this->createGoal('Test', null, 1000, 1000);
        
        $this->assertEquals(100.0, $this->goalManager->getProgressPercentage($goal));
    }

    /**
     * Test: Calcul du pourcentage de progression au-delà de 100% (limité à 100).
     */
    public function testGetProgressPercentageOverHundred(): void
    {
        $goal = $this->createGoal('Test', null, 1000, 1500);
        
        $this->assertEquals(100.0, $this->goalManager->getProgressPercentage($goal));
    }

    /**
     * Test: Objectif atteint.
     */
    public function testIsGoalReached(): void
    {
        $goal = $this->createGoal('Test', null, 1000, 1000);
        
        $this->assertTrue($this->goalManager->isGoalReached($goal));
    }

    /**
     * Test: Objectif non atteint.
     */
    public function testIsGoalNotReached(): void
    {
        $goal = $this->createGoal('Test', null, 1000, 500);
        
        $this->assertFalse($this->goalManager->isGoalReached($goal));
    }

    /**
     * Test: Objectif en retard.
     */
    public function testIsGoalOverdue(): void
    {
        $goal = $this->createGoal('Test', new \DateTime('-1 day'), 1000, 500);
        
        $this->assertTrue($this->goalManager->isGoalOverdue($goal));
    }

    /**
     * Test: Objectif non en retard (deadline future).
     */
    public function testIsGoalNotOverdue(): void
    {
        $goal = $this->createGoal('Test', new \DateTime('+10 days'), 1000, 500);
        
        $this->assertFalse($this->goalManager->isGoalOverdue($goal));
    }

    /**
     * Test: Objectif en retard mais atteint (non considéré en retard).
     */
    public function testIsGoalOverdueButReached(): void
    {
        $goal = $this->createGoal('Test', new \DateTime('-1 day'), 1000, 1000);
        
        $this->assertFalse($this->goalManager->isGoalOverdue($goal));
    }
}
