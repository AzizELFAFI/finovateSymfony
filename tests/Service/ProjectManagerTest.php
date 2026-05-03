<?php

namespace App\Tests\Service;

use App\Entity\Project;
use App\Service\ProjectManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le service ProjectManager.
 *
 * Règles métier testées :
 * 1. Le titre doit contenir au moins 3 caractères
 * 2. Le montant objectif (goal_amount) doit être strictement positif
 * 3. Le statut doit être l'un des statuts autorisés : open, funded, closed, cancelled
 * 4. La date limite (deadline) doit être dans le futur
 * 5. Le montant collecté (current_amount) ne peut pas dépasser le montant objectif
 */
class ProjectManagerTest extends TestCase
{
    private ProjectManager $projectManager;

    protected function setUp(): void
    {
        $this->projectManager = new ProjectManager();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Crée un projet valide prêt à être modifié dans chaque test.
     */
    private function makeValidProject(): Project
    {
        $project = new Project();
        $project->setTitle('Mon Projet');
        $project->setDescription('Description du projet.');
        $project->setGoalAmount('10000.00');
        $project->setCurrentAmount('2000.00');
        $project->setStatus('open');
        $project->setDeadline(new \DateTime('+30 days'));

        return $project;
    }

    // -------------------------------------------------------------------------
    // validate() — cas valides
    // -------------------------------------------------------------------------

    /**
     * Test : Un projet valide avec toutes les règles respectées.
     */
    public function testValidProject(): void
    {
        $project = $this->makeValidProject();

        $this->assertTrue($this->projectManager->validate($project));
    }

    /**
     * Test : Statut "funded" est accepté.
     */
    public function testValidProjectWithStatusFunded(): void
    {
        $project = $this->makeValidProject();
        $project->setStatus('funded');

        $this->assertTrue($this->projectManager->validate($project));
    }

    /**
     * Test : Statut "closed" est accepté.
     */
    public function testValidProjectWithStatusClosed(): void
    {
        $project = $this->makeValidProject();
        $project->setStatus('closed');
        $project->setDeadline(null); // closed n'a pas besoin de deadline future

        $this->assertTrue($this->projectManager->validate($project));
    }

    /**
     * Test : Statut "cancelled" est accepté.
     */
    public function testValidProjectWithStatusCancelled(): void
    {
        $project = $this->makeValidProject();
        $project->setStatus('cancelled');
        $project->setDeadline(null);

        $this->assertTrue($this->projectManager->validate($project));
    }

    /**
     * Test : Statut null est accepté (optionnel).
     */
    public function testValidProjectWithNullStatus(): void
    {
        $project = $this->makeValidProject();
        $project->setStatus(null);

        $this->assertTrue($this->projectManager->validate($project));
    }

    /**
     * Test : Deadline null est acceptée (optionnelle).
     */
    public function testValidProjectWithNullDeadline(): void
    {
        $project = $this->makeValidProject();
        $project->setDeadline(null);

        $this->assertTrue($this->projectManager->validate($project));
    }

    /**
     * Test : current_amount null est accepté.
     */
    public function testValidProjectWithNullCurrentAmount(): void
    {
        $project = $this->makeValidProject();
        $project->setCurrentAmount(null);

        $this->assertTrue($this->projectManager->validate($project));
    }

    /**
     * Test : current_amount égal au goal_amount est accepté (limite).
     */
    public function testValidProjectWithCurrentAmountEqualToGoal(): void
    {
        $project = $this->makeValidProject();
        $project->setGoalAmount('5000.00');
        $project->setCurrentAmount('5000.00');

        $this->assertTrue($this->projectManager->validate($project));
    }

    // -------------------------------------------------------------------------
    // validate() — titre invalide
    // -------------------------------------------------------------------------

    /**
     * Test : Titre trop court (moins de 3 caractères) doit lever une exception.
     */
    public function testProjectWithShortTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre du projet doit contenir au moins 3 caractères.');

        $project = $this->makeValidProject();
        $project->setTitle('AB'); // 2 caractères < 3

        $this->projectManager->validate($project);
    }

    /**
     * Test : Titre vide doit lever une exception.
     */
    public function testProjectWithEmptyTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre du projet doit contenir au moins 3 caractères.');

        $project = $this->makeValidProject();
        $project->setTitle('');

        $this->projectManager->validate($project);
    }

    /**
     * Test : Titre exactement 3 caractères est accepté (limite basse).
     */
    public function testProjectWithExactlyThreeCharactersTitle(): void
    {
        $project = $this->makeValidProject();
        $project->setTitle('ABC'); // exactement 3 caractères

        $this->assertTrue($this->projectManager->validate($project));
    }

    // -------------------------------------------------------------------------
    // validate() — montant objectif invalide
    // -------------------------------------------------------------------------

    /**
     * Test : Montant objectif à zéro doit lever une exception.
     */
    public function testProjectWithZeroGoalAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le montant objectif doit être strictement positif.');

        $project = $this->makeValidProject();
        $project->setGoalAmount('0');

        $this->projectManager->validate($project);
    }

    /**
     * Test : Montant objectif négatif doit lever une exception.
     */
    public function testProjectWithNegativeGoalAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le montant objectif doit être strictement positif.');

        $project = $this->makeValidProject();
        $project->setGoalAmount('-1000.00');

        $this->projectManager->validate($project);
    }

    // -------------------------------------------------------------------------
    // validate() — statut invalide
    // -------------------------------------------------------------------------

    /**
     * Test : Statut inconnu doit lever une exception.
     */
    public function testProjectWithInvalidStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le statut doit être l\'un des suivants');

        $project = $this->makeValidProject();
        $project->setStatus('invalid_status');

        $this->projectManager->validate($project);
    }

    // -------------------------------------------------------------------------
    // validate() — deadline invalide
    // -------------------------------------------------------------------------

    /**
     * Test : Deadline dans le passé doit lever une exception.
     */
    public function testProjectWithPastDeadline(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date limite du projet doit être dans le futur.');

        $project = $this->makeValidProject();
        $project->setDeadline(new \DateTime('-1 day'));

        $this->projectManager->validate($project);
    }

    // -------------------------------------------------------------------------
    // validate() — montant collecté invalide
    // -------------------------------------------------------------------------

    /**
     * Test : current_amount supérieur au goal_amount doit lever une exception.
     */
    public function testProjectWithCurrentAmountExceedingGoal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le montant collecté ne peut pas dépasser le montant objectif.');

        $project = $this->makeValidProject();
        $project->setGoalAmount('5000.00');
        $project->setCurrentAmount('6000.00'); // dépasse le goal

        $this->projectManager->validate($project);
    }

    // -------------------------------------------------------------------------
    // Méthodes individuelles
    // -------------------------------------------------------------------------

    /**
     * Test : isTitleValid retourne true pour un titre valide.
     */
    public function testIsTitleValidWithValidTitle(): void
    {
        $project = new Project();
        $project->setTitle('Mon Super Projet');

        $this->assertTrue($this->projectManager->isTitleValid($project));
    }

    /**
     * Test : isTitleValid retourne false pour un titre trop court.
     */
    public function testIsTitleValidWithShortTitle(): void
    {
        $project = new Project();
        $project->setTitle('AB');

        $this->assertFalse($this->projectManager->isTitleValid($project));
    }

    /**
     * Test : isGoalAmountValid retourne true pour un montant positif.
     */
    public function testIsGoalAmountValidWithPositiveAmount(): void
    {
        $project = new Project();
        $project->setGoalAmount('10000.00');

        $this->assertTrue($this->projectManager->isGoalAmountValid($project));
    }

    /**
     * Test : isGoalAmountValid retourne false pour un montant nul.
     */
    public function testIsGoalAmountValidWithZeroAmount(): void
    {
        $project = new Project();
        $project->setGoalAmount('0');

        $this->assertFalse($this->projectManager->isGoalAmountValid($project));
    }

    /**
     * Test : isStatusValid retourne true pour un statut autorisé.
     */
    public function testIsStatusValidWithValidStatus(): void
    {
        $project = new Project();
        $project->setStatus('open');

        $this->assertTrue($this->projectManager->isStatusValid($project));
    }

    /**
     * Test : isStatusValid retourne true pour null.
     */
    public function testIsStatusValidWithNull(): void
    {
        $project = new Project();
        $project->setStatus(null);

        $this->assertTrue($this->projectManager->isStatusValid($project));
    }

    /**
     * Test : isStatusValid retourne false pour un statut inconnu.
     */
    public function testIsStatusValidWithInvalidStatus(): void
    {
        $project = new Project();
        $project->setStatus('unknown');

        $this->assertFalse($this->projectManager->isStatusValid($project));
    }

    /**
     * Test : isDeadlineValid retourne true pour une deadline future.
     */
    public function testIsDeadlineValidWithFutureDate(): void
    {
        $project = new Project();
        $project->setDeadline(new \DateTime('+1 month'));

        $this->assertTrue($this->projectManager->isDeadlineValid($project));
    }

    /**
     * Test : isDeadlineValid retourne false pour une deadline passée.
     */
    public function testIsDeadlineValidWithPastDate(): void
    {
        $project = new Project();
        $project->setDeadline(new \DateTime('-1 day'));

        $this->assertFalse($this->projectManager->isDeadlineValid($project));
    }

    /**
     * Test : isDeadlineValid retourne true pour null.
     */
    public function testIsDeadlineValidWithNull(): void
    {
        $project = new Project();
        $project->setDeadline(null);

        $this->assertTrue($this->projectManager->isDeadlineValid($project));
    }

    /**
     * Test : isCurrentAmountValid retourne true quand current <= goal.
     */
    public function testIsCurrentAmountValidWhenBelowGoal(): void
    {
        $project = new Project();
        $project->setGoalAmount('10000.00');
        $project->setCurrentAmount('3000.00');

        $this->assertTrue($this->projectManager->isCurrentAmountValid($project));
    }

    /**
     * Test : isCurrentAmountValid retourne false quand current > goal.
     */
    public function testIsCurrentAmountValidWhenExceedsGoal(): void
    {
        $project = new Project();
        $project->setGoalAmount('5000.00');
        $project->setCurrentAmount('9999.00');

        $this->assertFalse($this->projectManager->isCurrentAmountValid($project));
    }

    /**
     * Test : isCurrentAmountValid retourne true pour null.
     */
    public function testIsCurrentAmountValidWithNull(): void
    {
        $project = new Project();
        $project->setGoalAmount('5000.00');
        $project->setCurrentAmount(null);

        $this->assertTrue($this->projectManager->isCurrentAmountValid($project));
    }
}
