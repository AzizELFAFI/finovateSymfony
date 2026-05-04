<?php

namespace App\Tests\Service;

use App\Entity\Investissement;
use App\Service\InvestissementManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le service InvestissementManager.
 *
 * Règles métier testées :
 * 1. Le montant doit être strictement positif
 * 2. Le statut doit être l'un des statuts autorisés : pending, active, completed, cancelled
 * 3. Le pourcentage de revenu doit être compris entre 0 et 100 (si renseigné)
 * 4. La date d'investissement ne peut pas être dans le futur
 */
class InvestissementManagerTest extends TestCase
{
    private InvestissementManager $investissementManager;

    protected function setUp(): void
    {
        $this->investissementManager = new InvestissementManager();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Crée un investissement valide prêt à être modifié dans chaque test.
     */
    private function makeValidInvestissement(): Investissement
    {
        $inv = new Investissement();
        $inv->setAmount('1000.00');
        $inv->setStatus('active');
        $inv->setRevenuePercentage(10.0);
        $inv->setInvestmentDate(new \DateTimeImmutable('-1 day'));

        return $inv;
    }

    // -------------------------------------------------------------------------
    // validate() — cas valides
    // -------------------------------------------------------------------------

    /**
     * Test : Un investissement valide avec toutes les règles respectées.
     */
    public function testValidInvestissement(): void
    {
        $inv = $this->makeValidInvestissement();

        $this->assertTrue($this->investissementManager->validate($inv));
    }

    /**
     * Test : Statut "pending" est accepté.
     */
    public function testValidInvestissementWithStatusPending(): void
    {
        $inv = $this->makeValidInvestissement();
        $inv->setStatus('pending');

        $this->assertTrue($this->investissementManager->validate($inv));
    }

    /**
     * Test : Statut "completed" est accepté.
     */
    public function testValidInvestissementWithStatusCompleted(): void
    {
        $inv = $this->makeValidInvestissement();
        $inv->setStatus('completed');

        $this->assertTrue($this->investissementManager->validate($inv));
    }

    /**
     * Test : Statut "cancelled" est accepté.
     */
    public function testValidInvestissementWithStatusCancelled(): void
    {
        $inv = $this->makeValidInvestissement();
        $inv->setStatus('cancelled');

        $this->assertTrue($this->investissementManager->validate($inv));
    }

    /**
     * Test : Pourcentage de revenu null est accepté.
     */
    public function testValidInvestissementWithNullRevenuePercentage(): void
    {
        $inv = $this->makeValidInvestissement();
        $inv->setRevenuePercentage(null);

        $this->assertTrue($this->investissementManager->validate($inv));
    }

    /**
     * Test : Pourcentage de revenu à 0 est accepté (limite basse).
     */
    public function testValidInvestissementWithZeroRevenuePercentage(): void
    {
        $inv = $this->makeValidInvestissement();
        $inv->setRevenuePercentage(0.0);

        $this->assertTrue($this->investissementManager->validate($inv));
    }

    /**
     * Test : Pourcentage de revenu à 100 est accepté (limite haute).
     */
    public function testValidInvestissementWithMaxRevenuePercentage(): void
    {
        $inv = $this->makeValidInvestissement();
        $inv->setRevenuePercentage(100.0);

        $this->assertTrue($this->investissementManager->validate($inv));
    }

    // -------------------------------------------------------------------------
    // validate() — montant invalide
    // -------------------------------------------------------------------------

    /**
     * Test : Montant à zéro doit lever une exception.
     */
    public function testInvestissementWithZeroAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le montant de l\'investissement doit être strictement positif.');

        $inv = $this->makeValidInvestissement();
        $inv->setAmount('0');

        $this->investissementManager->validate($inv);
    }

    /**
     * Test : Montant négatif doit lever une exception.
     */
    public function testInvestissementWithNegativeAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le montant de l\'investissement doit être strictement positif.');

        $inv = $this->makeValidInvestissement();
        $inv->setAmount('-500.00');

        $this->investissementManager->validate($inv);
    }

    // -------------------------------------------------------------------------
    // validate() — statut invalide
    // -------------------------------------------------------------------------

    /**
     * Test : Statut inconnu doit lever une exception.
     */
    public function testInvestissementWithInvalidStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le statut doit être l\'un des suivants');

        $inv = $this->makeValidInvestissement();
        $inv->setStatus('unknown_status');

        $this->investissementManager->validate($inv);
    }

    /**
     * Test : Statut vide doit lever une exception.
     */
    public function testInvestissementWithEmptyStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le statut doit être l\'un des suivants');

        $inv = $this->makeValidInvestissement();
        $inv->setStatus('');

        $this->investissementManager->validate($inv);
    }

    // -------------------------------------------------------------------------
    // validate() — pourcentage de revenu invalide
    // -------------------------------------------------------------------------

    /**
     * Test : Pourcentage de revenu négatif doit lever une exception.
     */
    public function testInvestissementWithNegativeRevenuePercentage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le pourcentage de revenu doit être compris entre 0 et 100.');

        $inv = $this->makeValidInvestissement();
        $inv->setRevenuePercentage(-5.0);

        $this->investissementManager->validate($inv);
    }

    /**
     * Test : Pourcentage de revenu supérieur à 100 doit lever une exception.
     */
    public function testInvestissementWithRevenuePercentageAbove100(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le pourcentage de revenu doit être compris entre 0 et 100.');

        $inv = $this->makeValidInvestissement();
        $inv->setRevenuePercentage(101.0);

        $this->investissementManager->validate($inv);
    }

    // -------------------------------------------------------------------------
    // validate() — date invalide
    // -------------------------------------------------------------------------

    /**
     * Test : Date d'investissement dans le futur doit lever une exception.
     */
    public function testInvestissementWithFutureDate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date d\'investissement ne peut pas être dans le futur.');

        $inv = $this->makeValidInvestissement();
        $inv->setInvestmentDate(new \DateTimeImmutable('+1 day'));

        $this->investissementManager->validate($inv);
    }

    // -------------------------------------------------------------------------
    // Méthodes individuelles
    // -------------------------------------------------------------------------

    /**
     * Test : isAmountValid retourne true pour un montant positif.
     */
    public function testIsAmountValidWithPositiveAmount(): void
    {
        $inv = new Investissement();
        $inv->setAmount('500.00');

        $this->assertTrue($this->investissementManager->isAmountValid($inv));
    }

    /**
     * Test : isAmountValid retourne false pour un montant nul.
     */
    public function testIsAmountValidWithZeroAmount(): void
    {
        $inv = new Investissement();
        $inv->setAmount('0');

        $this->assertFalse($this->investissementManager->isAmountValid($inv));
    }

    /**
     * Test : isStatusValid retourne true pour un statut autorisé.
     */
    public function testIsStatusValidWithValidStatus(): void
    {
        $inv = new Investissement();
        $inv->setStatus('active');

        $this->assertTrue($this->investissementManager->isStatusValid($inv));
    }

    /**
     * Test : isStatusValid retourne false pour un statut inconnu.
     */
    public function testIsStatusValidWithInvalidStatus(): void
    {
        $inv = new Investissement();
        $inv->setStatus('invalid');

        $this->assertFalse($this->investissementManager->isStatusValid($inv));
    }

    /**
     * Test : isRevenuePercentageValid retourne true pour null.
     */
    public function testIsRevenuePercentageValidWithNull(): void
    {
        $inv = new Investissement();
        $inv->setRevenuePercentage(null);

        $this->assertTrue($this->investissementManager->isRevenuePercentageValid($inv));
    }

    /**
     * Test : isRevenuePercentageValid retourne true pour 50%.
     */
    public function testIsRevenuePercentageValidWithFiftyPercent(): void
    {
        $inv = new Investissement();
        $inv->setRevenuePercentage(50.0);

        $this->assertTrue($this->investissementManager->isRevenuePercentageValid($inv));
    }

    /**
     * Test : isRevenuePercentageValid retourne false pour 150%.
     */
    public function testIsRevenuePercentageValidWithExcessiveValue(): void
    {
        $inv = new Investissement();
        $inv->setRevenuePercentage(150.0);

        $this->assertFalse($this->investissementManager->isRevenuePercentageValid($inv));
    }

    /**
     * Test : isDateValid retourne true pour une date passée.
     */
    public function testIsDateValidWithPastDate(): void
    {
        $inv = new Investissement();
        $inv->setInvestmentDate(new \DateTimeImmutable('-1 month'));

        $this->assertTrue($this->investissementManager->isDateValid($inv));
    }

    /**
     * Test : isDateValid retourne false pour une date future.
     */
    public function testIsDateValidWithFutureDate(): void
    {
        $inv = new Investissement();
        $inv->setInvestmentDate(new \DateTimeImmutable('+1 day'));

        $this->assertFalse($this->investissementManager->isDateValid($inv));
    }

    /**
     * Test : isDateValid retourne true pour null.
     */
    public function testIsDateValidWithNull(): void
    {
        $inv = new Investissement();
        $inv->setInvestmentDate(null);

        $this->assertTrue($this->investissementManager->isDateValid($inv));
    }
}
