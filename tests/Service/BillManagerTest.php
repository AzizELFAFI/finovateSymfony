<?php

namespace App\Tests\Service;

use App\Entity\Bill;
use App\Service\BillManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le service BillManager.
 * 
 * Règles métier testées:
 * 1. La référence doit contenir entre 3 et 50 caractères
 * 2. Le montant doit être supérieur à 0
 */
class BillManagerTest extends TestCase
{
    private BillManager $billManager;

    protected function setUp(): void
    {
        $this->billManager = new BillManager();
    }

    /**
     * Crée une facture avec les propriétés spécifiées.
     */
    private function createBill(string $reference, float $amount, ?\DateTimeInterface $paymentDate = null): Bill
    {
        $bill = new Bill();
        $bill->setId(1);
        $bill->setId_user(1);
        $bill->setReference($reference);
        $bill->setAmount($amount);
        $bill->setDate_paiement($paymentDate ?? new \DateTime('+7 days'));
        
        return $bill;
    }

    /**
     * Test: Facture valide avec toutes les règles respectées.
     */
    public function testValidBill(): void
    {
        $bill = $this->createBill('FACT-2024-001', 150.50);
        
        $this->assertTrue($this->billManager->validate($bill));
    }

    /**
     * Test: Référence trop courte (moins de 3 caractères).
     */
    public function testBillWithShortReference(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La référence doit contenir entre 3 et 50 caractères.');
        
        $bill = $this->createBill('AB', 100);
        
        $this->billManager->validate($bill);
    }

    /**
     * Test: Référence trop longue (plus de 50 caractères).
     */
    public function testBillWithLongReference(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La référence doit contenir entre 3 et 50 caractères.');
        
        $longReference = str_repeat('A', 51);
        $bill = $this->createBill($longReference, 100);
        
        $this->billManager->validate($bill);
    }

    /**
     * Test: Référence avec exactement 3 caractères (limite).
     */
    public function testBillWithExactlyThreeCharactersReference(): void
    {
        $bill = $this->createBill('ABC', 100);
        
        $this->assertTrue($this->billManager->validate($bill));
    }

    /**
     * Test: Référence avec exactement 50 caractères (limite).
     */
    public function testBillWithExactlyFiftyCharactersReference(): void
    {
        $reference = str_repeat('A', 50);
        $bill = $this->createBill($reference, 100);
        
        $this->assertTrue($this->billManager->validate($bill));
    }

    /**
     * Test: Montant égal à 0 (invalide).
     */
    public function testBillWithZeroAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le montant doit être supérieur à 0.');
        
        $bill = $this->createBill('FACT-001', 0);
        
        $this->billManager->validate($bill);
    }

    /**
     * Test: Montant négatif (invalide).
     */
    public function testBillWithNegativeAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le montant doit être supérieur à 0.');
        
        $bill = $this->createBill('FACT-001', -50);
        
        $this->billManager->validate($bill);
    }

    /**
     * Test: Montant décimal (valide).
     */
    public function testBillWithDecimalAmount(): void
    {
        $bill = $this->createBill('FACT-001', 99.99);
        
        $this->assertTrue($this->billManager->validate($bill));
    }

    /**
     * Test: Montant très élevé (valide).
     */
    public function testBillWithHighAmount(): void
    {
        $bill = $this->createBill('FACT-001', 999999.99);
        
        $this->assertTrue($this->billManager->validate($bill));
    }

    /**
     * Test: Méthode isReferenceValid avec référence valide.
     */
    public function testIsReferenceValidWithValidReference(): void
    {
        $bill = $this->createBill('FACT-2024-001', 100);
        
        $this->assertTrue($this->billManager->isReferenceValid($bill));
    }

    /**
     * Test: Méthode isReferenceValid avec référence invalide.
     */
    public function testIsReferenceValidWithInvalidReference(): void
    {
        $bill = $this->createBill('AB', 100);
        
        $this->assertFalse($this->billManager->isReferenceValid($bill));
    }

    /**
     * Test: Méthode isAmountValid avec montant valide.
     */
    public function testIsAmountValidWithValidAmount(): void
    {
        $bill = $this->createBill('FACT-001', 100);
        
        $this->assertTrue($this->billManager->isAmountValid($bill));
    }

    /**
     * Test: Méthode isAmountValid avec montant invalide.
     */
    public function testIsAmountValidWithInvalidAmount(): void
    {
        $bill = $this->createBill('FACT-001', 0);
        
        $this->assertFalse($this->billManager->isAmountValid($bill));
    }

    /**
     * Test: Date de paiement dans le futur.
     */
    public function testIsPaymentDateInFuture(): void
    {
        $bill = $this->createBill('FACT-001', 100, new \DateTime('+7 days'));
        
        $this->assertTrue($this->billManager->isPaymentDateInFuture($bill));
    }

    /**
     * Test: Date de paiement dans le passé.
     */
    public function testIsPaymentDateNotInFuture(): void
    {
        $bill = $this->createBill('FACT-001', 100, new \DateTime('-1 day'));
        
        $this->assertFalse($this->billManager->isPaymentDateInFuture($bill));
    }

    /**
     * Test: Date de paiement aujourd'hui.
     */
    public function testIsPaymentDateToday(): void
    {
        $bill = $this->createBill('FACT-001', 100, new \DateTime('today'));
        
        $this->assertTrue($this->billManager->isPaymentDateToday($bill));
    }

    /**
     * Test: Date de paiement pas aujourd'hui.
     */
    public function testIsPaymentDateNotToday(): void
    {
        $bill = $this->createBill('FACT-001', 100, new \DateTime('+1 day'));
        
        $this->assertFalse($this->billManager->isPaymentDateToday($bill));
    }

    /**
     * Test: Facture en retard.
     */
    public function testIsBillOverdue(): void
    {
        $bill = $this->createBill('FACT-001', 100, new \DateTime('-1 day'));
        
        $this->assertTrue($this->billManager->isBillOverdue($bill));
    }

    /**
     * Test: Facture non en retard (date future).
     */
    public function testIsBillNotOverdue(): void
    {
        $bill = $this->createBill('FACT-001', 100, new \DateTime('+7 days'));
        
        $this->assertFalse($this->billManager->isBillOverdue($bill));
    }

    /**
     * Test: Facture non en retard (date aujourd'hui).
     */
    public function testIsBillNotOverdueToday(): void
    {
        $bill = $this->createBill('FACT-001', 100, new \DateTime('today'));
        
        $this->assertFalse($this->billManager->isBillOverdue($bill));
    }

    /**
     * Test: Référence avec espaces (trim appliqué).
     */
    public function testBillWithTrimmedReference(): void
    {
        $bill = $this->createBill('  FACT-001  ', 100);
        
        // Le validateur trim la référence, donc elle devient "FACT-001" (8 caractères)
        $this->assertTrue($this->billManager->validate($bill));
    }
}
