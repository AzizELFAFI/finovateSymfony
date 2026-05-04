<?php

namespace App\Tests\Service;

use App\Entity\Goal;
use App\Entity\User;
use App\Service\GoalManager;
use App\Service\GoalService;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le service GoalService.
 * 
 * Règles métier testées:
 * 1. L'utilisateur doit être propriétaire de l'objectif
 * 2. L'objectif ne doit pas être déjà terminé
 * 3. L'utilisateur doit avoir un solde suffisant
 */
class GoalServiceTest extends TestCase
{
    private GoalService $goalService;
    private EntityManagerInterface&MockObject $entityManager;
    private GoalManager $goalManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->goalManager = new GoalManager();
        $this->goalService = new GoalService($this->entityManager, $this->goalManager);
    }

    /**
     * Crée un utilisateur avec les propriétés spécifiées.
     */
    private function createUser(int $id, float $solde): User
    {
        $user = new User();
        $user->setId((string) $id);
        $user->setEmail('user' . $id . '@test.com');
        $user->setPassword(hash('sha256', 'password'));
        $user->setFirstname('User' . $id);
        $user->setLastname('Test');
        $user->setRole('USER');
        $user->setBirthdate(new \DateTime('-20 years'));
        $user->setCin('1234567' . $id);
        $user->setPhone_number(12345678 + $id);
        $user->setPoints(0);
        $user->setSolde((string) $solde);
        $user->setCreated_at(new \DateTime());
        $user->setNumero_carte('123456789012345' . $id);
        
        return $user;
    }

    /**
     * Crée un objectif avec les propriétés spécifiées.
     */
    private function createGoal(int $id, int $userId, float $targetAmount, float $currentAmount = 0, string $status = 'IN_PROGRESS'): Goal
    {
        $goal = new Goal();
        $goal->setId($id);
        $goal->setId_user($userId);
        $goal->setTitle('Test Goal');
        $goal->setDeadline(new \DateTime('+30 days'));
        $goal->setCreated_at(new \DateTime());
        $goal->setStatus($status);
        $goal->setTarget_amount((string) $targetAmount);
        $goal->setCurrent_amount((string) $currentAmount);
        
        return $goal;
    }

    /**
     * Test: Création d'objectif réussie.
     */
    public function testCreateGoalSuccess(): void
    {
        $user = $this->createUser(1, 1000.0);

        $this->mockGenerateUniqueId(1);

        $this->entityManager->expects($this->once())
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->goalService->createGoal(
            $user,
            'Vacances été',
            1000.0,
            new \DateTime('+30 days')
        );

        $this->assertTrue($result['success']);
        $this->assertInstanceOf(Goal::class, $result['goal']);
    }

    /**
     * Test: Création d'objectif avec titre trop court.
     */
    public function testCreateGoalWithShortTitle(): void
    {
        $user = $this->createUser(1, 1000.0);

        $result = $this->goalService->createGoal(
            $user,
            'AB', // Titre trop court
            1000.0,
            new \DateTime('+30 days')
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('3 et 40', $result['message']);
    }

    /**
     * Test: Création d'objectif avec deadline passée.
     */
    public function testCreateGoalWithPastDeadline(): void
    {
        $user = $this->createUser(1, 1000.0);

        $result = $this->goalService->createGoal(
            $user,
            'Test Goal',
            1000.0,
            new \DateTime('-1 day')
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('futur', $result['message']);
    }

    /**
     * Test: Création d'objectif avec montant objectif invalide.
     */
    public function testCreateGoalWithInvalidTargetAmount(): void
    {
        $user = $this->createUser(1, 1000.0);

        $result = $this->goalService->createGoal(
            $user,
            'Test Goal',
            0.0, // Montant invalide
            new \DateTime('+30 days')
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('supérieur à 0', $result['message']);
    }

    /**
     * Test: Ajout de montant réussi.
     */
    public function testAddAmountSuccess(): void
    {
        $user = $this->createUser(1, 500.0);
        $goal = $this->createGoal(1, 1, 1000.0, 0);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->goalService->addAmount($goal, $user, 100.0);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['completed']);
        $this->assertEquals('100', $goal->getCurrent_amount());
        $this->assertEquals('400', $user->getSolde());
    }

    /**
     * Test: Ajout de montant - objectif atteint.
     */
    public function testAddAmountGoalCompleted(): void
    {
        $user = $this->createUser(1, 500.0);
        $goal = $this->createGoal(1, 1, 100.0, 50.0);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->goalService->addAmount($goal, $user, 50.0);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['completed']);
        $this->assertEquals('COMPLETED', $goal->getStatus());
    }

    /**
     * Test: Ajout de montant - utilisateur non propriétaire.
     */
    public function testAddAmountNotOwner(): void
    {
        $user = $this->createUser(1, 500.0);
        $goal = $this->createGoal(1, 2, 1000.0, 0); // Appartient à l'utilisateur 2

        $result = $this->goalService->addAmount($goal, $user, 100.0);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('propriétaire', $result['message']);
    }

    /**
     * Test: Ajout de montant - objectif déjà terminé.
     */
    public function testAddAmountGoalAlreadyCompleted(): void
    {
        $user = $this->createUser(1, 500.0);
        $goal = $this->createGoal(1, 1, 1000.0, 1000.0, 'COMPLETED');

        $result = $this->goalService->addAmount($goal, $user, 100.0);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('déjà terminé', $result['message']);
    }

    /**
     * Test: Ajout de montant - solde insuffisant.
     */
    public function testAddAmountInsufficientBalance(): void
    {
        $user = $this->createUser(1, 50.0); // Solde insuffisant
        $goal = $this->createGoal(1, 1, 1000.0, 0);

        $result = $this->goalService->addAmount($goal, $user, 100.0);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Solde insuffisant', $result['message']);
    }

    /**
     * Test: getRemainingAmount.
     */
    public function testGetRemainingAmount(): void
    {
        $goal = $this->createGoal(1, 1, 1000.0, 300.0);

        $remaining = $this->goalService->getRemainingAmount($goal);

        $this->assertEquals(700.0, $remaining);
    }

    /**
     * Test: getRemainingAmount - objectif atteint.
     */
    public function testGetRemainingAmountGoalReached(): void
    {
        $goal = $this->createGoal(1, 1, 1000.0, 1000.0);

        $remaining = $this->goalService->getRemainingAmount($goal);

        $this->assertEquals(0.0, $remaining);
    }

    /**
     * Test: getRemainingAmount - objectif dépassé.
     */
    public function testGetRemainingAmountGoalExceeded(): void
    {
        $goal = $this->createGoal(1, 1, 1000.0, 1200.0);

        $remaining = $this->goalService->getRemainingAmount($goal);

        $this->assertEquals(0.0, $remaining);
    }

    /**
     * Mock le query builder pour generateUniqueId.
     */
    private function mockGenerateUniqueId(int $id): void
    {
        $query = $this->createMock(Query::class);
        $query->expects($this->any())
            ->method('getSingleColumnResult')
            ->willReturn([]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->any())
            ->method('select')
            ->willReturnSelf();
        $queryBuilder->expects($this->any())
            ->method('from')
            ->willReturnSelf();
        $queryBuilder->expects($this->any())
            ->method('orderBy')
            ->willReturnSelf();
        $queryBuilder->expects($this->any())
            ->method('getQuery')
            ->willReturn($query);

        $this->entityManager->expects($this->any())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);
    }
}
