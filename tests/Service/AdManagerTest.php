<?php

namespace App\Tests\Service;

use App\Entity\Ad;
use App\Entity\UserAdClick;
use App\Service\AdManager;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le service AdManager.
 * 
 * Règles métier testées:
 * 1. Le titre est obligatoire
 * 2. La durée doit être positive
 * 3. Les points de récompense doivent être >= 0
 */
class AdManagerTest extends TestCase
{
    private AdManager $adManager;

    protected function setUp(): void
    {
        $this->adManager = new AdManager();
    }

    /**
     * Crée une annonce avec les propriétés spécifiées.
     */
    private function createAd(string $title, int $duration, int $rewardPoints): Ad
    {
        $ad = new Ad();
        $ad->setTitle($title);
        $ad->setDuration($duration);
        $ad->setRewardPoints($rewardPoints);
        $ad->setImagePath('/uploads/ads/test');
        
        return $ad;
    }

    /**
     * Test: Annonce valide avec toutes les règles respectées.
     */
    public function testValidAd(): void
    {
        
        $ad = $this->createAd('Annonce Test', 2, 10);
        
        $this->assertTrue($this->adManager->validate($ad));
    }

    /**
     * Test: Titre vide (invalide).
     */
    public function testAdWithEmptyTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre de l\'annonce est obligatoire.');
        
        $ad = $this->createAd('', 30, 10);
        
        $this->adManager->validate($ad);
    }

    /**
     * Test: Titre avec espaces uniquement (invalide).
     */
    public function testAdWithWhitespaceTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre de l\'annonce est obligatoire.');
        
        $ad = $this->createAd('   ', 30, 10);
        
        $this->adManager->validate($ad);
    }

    /**
     * Test: Durée égale à 0 (invalide).
     */
    public function testAdWithZeroDuration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La durée doit être supérieure à 0.');
        
        $ad = $this->createAd('Annonce Test', 0, 10);
        
        $this->adManager->validate($ad);
    }

    /**
     * Test: Durée négative (invalide).
     */
    public function testAdWithNegativeDuration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La durée doit être supérieure à 0.');
        
        $ad = $this->createAd('Annonce Test', -5, 10);
        
        $this->adManager->validate($ad);
    }

    /**
     * Test: Points de récompense négatifs (invalide).
     */
    public function testAdWithNegativeRewardPoints(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Les points de récompense ne peuvent pas être négatifs.');
        
        $ad = $this->createAd('Annonce Test', 30, -5);
        
        $this->adManager->validate($ad);
    }

    /**
     * Test: Points de récompense égaux à 0 (valide).
     */
    public function testAdWithZeroRewardPoints(): void
    {
        $ad = $this->createAd('Annonce Test', 30, 0);
        
        $this->assertTrue($this->adManager->validate($ad));
    }

    /**
     * Test: Méthode isTitleValid avec titre valide.
     */
    public function testIsTitleValidWithValidTitle(): void
    {
        $ad = $this->createAd('Annonce Test', 30, 10);
        
        $this->assertTrue($this->adManager->isTitleValid($ad));
    }

    /**
     * Test: Méthode isTitleValid avec titre invalide.
     */
    public function testIsTitleValidWithInvalidTitle(): void
    {
        $ad = $this->createAd('', 30, 10);
        
        $this->assertFalse($this->adManager->isTitleValid($ad));
    }

    /**
     * Test: Méthode isDurationValid avec durée valide.
     */
    public function testIsDurationValidWithValidDuration(): void
    {
        $ad = $this->createAd('Annonce Test', 30, 10);
        
        $this->assertTrue($this->adManager->isDurationValid($ad));
    }

    /**
     * Test: Méthode isDurationValid avec durée invalide.
     */
    public function testIsDurationValidWithInvalidDuration(): void
    {
        $ad = $this->createAd('Annonce Test', 0, 10);
        
        $this->assertFalse($this->adManager->isDurationValid($ad));
    }

    /**
     * Test: Méthode isRewardPointsValid avec points valides.
     */
    public function testIsRewardPointsValidWithValidPoints(): void
    {
        $ad = $this->createAd('Annonce Test', 30, 10);
        
        $this->assertTrue($this->adManager->isRewardPointsValid($ad));
    }

    /**
     * Test: Méthode isRewardPointsValid avec points négatifs.
     */
    public function testIsRewardPointsValidWithNegativePoints(): void
    {
        $ad = $this->createAd('Annonce Test', 30, -5);
        
        $this->assertFalse($this->adManager->isRewardPointsValid($ad));
    }

    /**
     * Test: Méthode hasReward avec points > 0.
     */
    public function testHasRewardWithPoints(): void
    {
        $ad = $this->createAd('Annonce Test', 30, 10);
        
        $this->assertTrue($this->adManager->hasReward($ad));
    }

    /**
     * Test: Méthode hasReward avec points = 0.
     */
    public function testHasRewardWithZeroPoints(): void
    {
        $ad = $this->createAd('Annonce Test', 30, 0);
        
        $this->assertFalse($this->adManager->hasReward($ad));
    }

    /**
     * Test: Méthode getClickCount.
     */
    public function testGetClickCount(): void
    {
        $ad = $this->createAd('Annonce Test', 30, 10);
        
        // Ajouter des clics mock
        $click1 = $this->createMock(UserAdClick::class);
        $click2 = $this->createMock(UserAdClick::class);
        
        $ad->addUserAdClick($click1);
        $ad->addUserAdClick($click2);
        
        $this->assertEquals(2, $this->adManager->getClickCount($ad));
    }

    /**
     * Test: Méthode getClickCount sans clics.
     */
    public function testGetClickCountWithNoClicks(): void
    {
        $ad = $this->createAd('Annonce Test', 30, 10);
        
        $this->assertEquals(0, $this->adManager->getClickCount($ad));
    }

    /**
     * Test: Méthode hasUserClicked avec utilisateur ayant cliqué.
     */
    public function testHasUserClickedWithClick(): void
    {
        $ad = $this->createAd('Annonce Test', 30, 10);
        
        $user = $this->createMock(\App\Entity\User::class);
        $user->method('getId')->willReturn('1');
        
        $click = $this->createMock(UserAdClick::class);
        $click->method('getUser')->willReturn($user);
        
        $ad->addUserAdClick($click);
        
        $this->assertTrue($this->adManager->hasUserClicked($ad, 1));
    }

    /**
     * Test: Méthode hasUserClicked avec utilisateur n'ayant pas cliqué.
     */
    public function testHasUserClickedWithNoClick(): void
    {
        $ad = $this->createAd('Annonce Test', 30, 10);
        
        $user = $this->createMock(\App\Entity\User::class);
        $user->method('getId')->willReturn('1');
        
        $click = $this->createMock(UserAdClick::class);
        $click->method('getUser')->willReturn($user);
        
        $ad->addUserAdClick($click);
        
        $this->assertFalse($this->adManager->hasUserClicked($ad, 2));
    }

    /**
     * Test: Annonce avec durée très longue (valide).
     */
    public function testAdWithLongDuration(): void
    {
        $ad = $this->createAd('Annonce Test', 365, 100);
        
        $this->assertTrue($this->adManager->validate($ad));
    }

    /**
     * Test: Annonce avec beaucoup de points de récompense (valide).
     */
    public function testAdWithHighRewardPoints(): void
    {
        $ad = $this->createAd('Annonce Test', 30, 1000);
        
        $this->assertTrue($this->adManager->validate($ad));
    }
}
