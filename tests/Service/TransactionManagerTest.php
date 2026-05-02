<?php

namespace App\Tests\Service;

use App\Entity\Transaction;
use App\Service\TransactionManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le service TransactionManager.
 * 
 * Règles métier testées:
 * 1. Le montant doit être supérieur à 0
 * 2. Le type est obligatoire
 * 3. La description est obligatoire
 */
class TransactionManagerTest extends TestCase
{
    private TransactionManager $transactionManager;

    protected function setUp(): void
    {
        $this->transactionManager = new TransactionManager();
    }

    /**
     * Crée une transaction avec les propriétés spécifiées.
     */
    private function createTransaction(float $amount, string $type = 'TRANSFER', string $description = 'Test transaction'): Transaction
    {
        $transaction = new Transaction();
        $transaction->setSender_id(1);
        $transaction->setReceiver_id(2);
        $transaction->setAmount((string) $amount);
        $transaction->setType($type);
        $transaction->setDescription($description);
        $transaction->setDate(new \DateTime());
        
        return $transaction;
    }

    /**
     * Test: Transaction valide avec toutes les règles respectées.
     */
    public function testValidTransaction(): void
    {
        $transaction = $this->createTransaction(100.50);
        
        $this->assertTrue($this->transactionManager->validate($transaction));
    }

    /**
     * Test: Montant égal à 0 (invalide).
     */
    public function testTransactionWithZeroAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le montant doit être supérieur à 0.');
        
        $transaction = $this->createTransaction(0);
        
        $this->transactionManager->validate($transaction);
    }

    /**
     * Test: Montant négatif (invalide).
     */
    public function testTransactionWithNegativeAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le montant doit être supérieur à 0.');
        
        $transaction = $this->createTransaction(-50);
        
        $this->transactionManager->validate($transaction);
    }

    /**
     * Test: Type vide (invalide).
     */
    public function testTransactionWithEmptyType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le type de transaction est obligatoire.');
        
        $transaction = $this->createTransaction(100, '');
        
        $this->transactionManager->validate($transaction);
    }

    /**
     * Test: Type avec espaces uniquement (invalide).
     */
    public function testTransactionWithWhitespaceType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le type de transaction est obligatoire.');
        
        $transaction = $this->createTransaction(100, '   ');
        
        $this->transactionManager->validate($transaction);
    }

    /**
     * Test: Description vide (invalide).
     */
    public function testTransactionWithEmptyDescription(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La description est obligatoire.');
        
        $transaction = $this->createTransaction(100, 'TRANSFER', '');
        
        $this->transactionManager->validate($transaction);
    }

    /**
     * Test: Description avec espaces uniquement (invalide).
     */
    public function testTransactionWithWhitespaceDescription(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La description est obligatoire.');
        
        $transaction = $this->createTransaction(100, 'TRANSFER', '   ');
        
        $this->transactionManager->validate($transaction);
    }

    /**
     * Test: Montant très élevé (valide).
     */
    public function testTransactionWithHighAmount(): void
    {
        $transaction = $this->createTransaction(999999.99);
        
        $this->assertTrue($this->transactionManager->validate($transaction));
    }

    /**
     * Test: Montant décimal (valide).
     */
    public function testTransactionWithDecimalAmount(): void
    {
        $transaction = $this->createTransaction(10.55);
        
        $this->assertTrue($this->transactionManager->validate($transaction));
    }

    /**
     * Test: Méthode isAmountValid avec montant valide.
     */
    public function testIsAmountValidWithValidAmount(): void
    {
        $transaction = $this->createTransaction(100);
        
        $this->assertTrue($this->transactionManager->isAmountValid($transaction));
    }

    /**
     * Test: Méthode isAmountValid avec montant invalide.
     */
    public function testIsAmountValidWithInvalidAmount(): void
    {
        $transaction = $this->createTransaction(-10);
        
        $this->assertFalse($this->transactionManager->isAmountValid($transaction));
    }

    /**
     * Test: Méthode isTypeValid avec type valide.
     */
    public function testIsTypeValidWithValidType(): void
    {
        $transaction = $this->createTransaction(100, 'TRANSFER');
        
        $this->assertTrue($this->transactionManager->isTypeValid($transaction));
    }

    /**
     * Test: Méthode isTypeValid avec type invalide.
     */
    public function testIsTypeValidWithInvalidType(): void
    {
        $transaction = $this->createTransaction(100, '');
        
        $this->assertFalse($this->transactionManager->isTypeValid($transaction));
    }

    /**
     * Test: Méthode isDescriptionValid avec description valide.
     */
    public function testIsDescriptionValidWithValidDescription(): void
    {
        $transaction = $this->createTransaction(100, 'TRANSFER', 'Paiement test');
        
        $this->assertTrue($this->transactionManager->isDescriptionValid($transaction));
    }

    /**
     * Test: Méthode isDescriptionValid avec description invalide.
     */
    public function testIsDescriptionValidWithInvalidDescription(): void
    {
        $transaction = $this->createTransaction(100, 'TRANSFER', '');
        
        $this->assertFalse($this->transactionManager->isDescriptionValid($transaction));
    }

    /**
     * Test: Expéditeur et bénéficiaire différents.
     */
    public function testAreSenderAndReceiverDifferent(): void
    {
        $transaction = $this->createTransaction(100);
        $transaction->setSender_id(1);
        $transaction->setReceiver_id(2);
        
        $this->assertTrue($this->transactionManager->areSenderAndReceiverDifferent($transaction));
    }

    /**
     * Test: Expéditeur et bénéficiaire identiques.
     */
    public function testAreSenderAndReceiverSame(): void
    {
        $transaction = $this->createTransaction(100);
        $transaction->setSender_id(1);
        $transaction->setReceiver_id(1);
        
        $this->assertFalse($this->transactionManager->areSenderAndReceiverDifferent($transaction));
    }
}
