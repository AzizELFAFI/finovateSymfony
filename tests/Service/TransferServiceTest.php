<?php

namespace App\Tests\Service;

use App\Entity\Transaction;
use App\Entity\User;
use App\Service\TransactionManager;
use App\Service\TransferService;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le service TransferService.
 * 
 * Règles métier testées:
 * 1. L'expéditeur et le bénéficiaire doivent être différents
 * 2. Le montant doit être positif
 * 3. L'expéditeur doit avoir un solde suffisant
 * 4. La limite journalière ne doit pas être dépassée (3000 TND)
 */
class TransferServiceTest extends TestCase
{
    private TransferService $transferService;
    private EntityManagerInterface&MockObject $entityManager;
    private TransactionManager $transactionManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->transactionManager = new TransactionManager();
        $this->transferService = new TransferService($this->entityManager, $this->transactionManager);
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
     * Test: Transfert réussi.
     */
    public function testSuccessfulTransfer(): void
    {
        $sender = $this->createUser(1, 500.0);
        $receiver = $this->createUser(2, 100.0);

        // Mock le query builder pour getTotalSentToday
        $this->mockGetTotalSentToday(0);

        $this->entityManager->expects($this->once())
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->transferService->transfer($sender, $receiver, 100.0, 'Test transfer');

        $this->assertTrue($result['success']);
        $this->assertInstanceOf(Transaction::class, $result['transaction']);
        $this->assertEquals('400', $sender->getSolde()); // 500 - 100 = 400
        $this->assertEquals('200', $receiver->getSolde()); // 100 + 100 = 200
    }

    /**
     * Test: Transfert à soi-même (invalide).
     */
    public function testTransferToSelf(): void
    {
        $user = $this->createUser(1, 500.0);

        $result = $this->transferService->transfer($user, $user, 100.0, 'Test transfer');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('vous-même', $result['message']);
        $this->assertEquals(422, $result['code']);
    }

    /**
     * Test: Montant négatif (invalide).
     */
    public function testTransferWithNegativeAmount(): void
    {
        $sender = $this->createUser(1, 500.0);
        $receiver = $this->createUser(2, 100.0);

        $result = $this->transferService->transfer($sender, $receiver, -50.0, 'Test transfer');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('supérieur à 0', $result['message']);
        $this->assertEquals(422, $result['code']);
    }

    /**
     * Test: Montant égal à 0 (invalide).
     */
    public function testTransferWithZeroAmount(): void
    {
        $sender = $this->createUser(1, 500.0);
        $receiver = $this->createUser(2, 100.0);

        $result = $this->transferService->transfer($sender, $receiver, 0.0, 'Test transfer');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('supérieur à 0', $result['message']);
        $this->assertEquals(422, $result['code']);
    }

    /**
     * Test: Solde insuffisant.
     */
    public function testTransferWithInsufficientBalance(): void
    {
        $sender = $this->createUser(1, 50.0);
        $receiver = $this->createUser(2, 100.0);

        $result = $this->transferService->transfer($sender, $receiver, 100.0, 'Test transfer');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Solde insuffisant', $result['message']);
        $this->assertEquals(422, $result['code']);
    }

    /**
     * Test: Limite journalière dépassée.
     */
    public function testTransferExceedingDailyLimit(): void
    {
        $sender = $this->createUser(1, 5000.0);
        $receiver = $this->createUser(2, 100.0);

        // Mock: utilisateur a déjà envoyé 2500 aujourd'hui
        $this->mockGetTotalSentToday(2500.0);

        $result = $this->transferService->transfer($sender, $receiver, 1000.0, 'Test transfer');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Limite journalière', $result['message']);
        $this->assertEquals(422, $result['code']);
    }

    /**
     * Test: Limite journalière exacte (3000).
     */
    public function testTransferAtDailyLimit(): void
    {
        $sender = $this->createUser(1, 5000.0);
        $receiver = $this->createUser(2, 100.0);

        // Mock: utilisateur a déjà envoyé 3000 aujourd'hui
        $this->mockGetTotalSentToday(3000.0);

        $result = $this->transferService->transfer($sender, $receiver, 100.0, 'Test transfer');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Limite journalière', $result['message']);
    }

    /**
     * Test: Transfert qui atteint la limite journalière exactement.
     */
    public function testTransferReachingDailyLimit(): void
    {
        $sender = $this->createUser(1, 5000.0);
        $receiver = $this->createUser(2, 100.0);

        // Mock: utilisateur a déjà envoyé 2900 aujourd'hui
        $this->mockGetTotalSentToday(2900.0);

        $this->entityManager->expects($this->once())
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->transferService->transfer($sender, $receiver, 100.0, 'Test transfer');

        $this->assertTrue($result['success']);
    }

    /**
     * Test: canSendAmount avec solde suffisant.
     */
    public function testCanSendAmountWithSufficientBalance(): void
    {
        $user = $this->createUser(1, 500.0);
        $this->mockGetTotalSentToday(0);

        $result = $this->transferService->canSendAmount($user, 100.0);

        $this->assertTrue($result['can_send']);
        $this->assertNull($result['reason']);
    }

    /**
     * Test: canSendAmount avec solde insuffisant.
     */
    public function testCanSendAmountWithInsufficientBalance(): void
    {
        $user = $this->createUser(1, 50.0);
        $this->mockGetTotalSentToday(0);

        $result = $this->transferService->canSendAmount($user, 100.0);

        $this->assertFalse($result['can_send']);
        $this->assertStringContainsString('Solde insuffisant', $result['reason']);
    }

    /**
     * Test: getRemainingDailyLimit.
     */
    public function testGetRemainingDailyLimit(): void
    {
        $user = $this->createUser(1, 5000.0);
        $this->mockGetTotalSentToday(1000.0);

        $remaining = $this->transferService->getRemainingDailyLimit($user);

        // min(3000 - 1000, 5000) = 2000
        $this->assertEquals(2000.0, $remaining);
    }

    /**
     * Test: getRemainingDailyLimit avec solde inférieur à la limite.
     */
    public function testGetRemainingDailyLimitWithLowBalance(): void
    {
        $user = $this->createUser(1, 500.0);
        $this->mockGetTotalSentToday(1000.0);

        $remaining = $this->transferService->getRemainingDailyLimit($user);

        // min(3000 - 1000, 500) = 500
        $this->assertEquals(500.0, $remaining);
    }

    /**
     * Mock le query builder pour getTotalSentToday.
     */
    private function mockGetTotalSentToday(float $amount): void
    {
        $query = $this->createMock(Query::class);
        $query->expects($this->any())
            ->method('getSingleScalarResult')
            ->willReturn($amount);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->any())
            ->method('select')
            ->willReturnSelf();
        $queryBuilder->expects($this->any())
            ->method('from')
            ->willReturnSelf();
        $queryBuilder->expects($this->any())
            ->method('where')
            ->willReturnSelf();
        $queryBuilder->expects($this->any())
            ->method('andWhere')
            ->willReturnSelf();
        $queryBuilder->expects($this->any())
            ->method('setParameter')
            ->willReturnSelf();
        $queryBuilder->expects($this->any())
            ->method('getQuery')
            ->willReturn($query);

        $this->entityManager->expects($this->any())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);
    }
}
