<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\UserManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le service UserManager.
 * 
 * Règles métier testées:
 * 1. Le mot de passe doit contenir au moins 8 caractères
 * 2. L'utilisateur doit avoir au moins 18 ans
 * 3. Le CIN doit contenir exactement 8 chiffres
 */
class UserManagerTest extends TestCase
{
    private UserManager $userManager;

    protected function setUp(): void
    {
        $this->userManager = new UserManager();
    }

    /**
     * Test: Un utilisateur valide avec toutes les règles respectées.
     */
    public function testValidUser(): void
    {
        $user = new User();
        $user->setPassword('password123'); // 11 caractères >= 8
        $user->setBirthdate(new \DateTime('-20 years')); // 20 ans >= 18
        $user->setCin('12345678'); // 8 chiffres

        $this->assertTrue($this->userManager->validate($user));
    }

    /**
     * Test: Mot de passe trop court (moins de 8 caractères).
     */
    public function testUserWithShortPassword(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le mot de passe doit contenir au moins 8 caractères.');

        $user = new User();
        $user->setPassword('pass12'); // 6 caractères < 8
        $user->setBirthdate(new \DateTime('-20 years'));
        $user->setCin('12345678');

        $this->userManager->validate($user);
    }

    /**
     * Test: Utilisateur mineur (moins de 18 ans).
     */
    public function testUserUnderage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'utilisateur doit avoir au moins 18 ans.');

        $user = new User();
        $user->setPassword('password123');
        $user->setBirthdate(new \DateTime('-16 years')); // 16 ans < 18
        $user->setCin('12345678');

        $this->userManager->validate($user);
    }

    /**
     * Test: CIN invalide (pas exactement 8 chiffres).
     */
    public function testUserWithInvalidCin(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le CIN doit contenir exactement 8 chiffres.');

        $user = new User();
        $user->setPassword('password123');
        $user->setBirthdate(new \DateTime('-20 years'));
        $user->setCin('1234567'); // 7 chiffres au lieu de 8

        $this->userManager->validate($user);
    }

    /**
     * Test: CIN avec lettres (invalide).
     */
    public function testUserWithCinContainingLetters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le CIN doit contenir exactement 8 chiffres.');

        $user = new User();
        $user->setPassword('password123');
        $user->setBirthdate(new \DateTime('-20 years'));
        $user->setCin('12345abc'); // contient des lettres

        $this->userManager->validate($user);
    }

    /**
     * Test: Mot de passe exactement 8 caractères (limite).
     */
    public function testUserWithExactlyEightCharactersPassword(): void
    {
        $user = new User();
        $user->setPassword('12345678'); // exactement 8 caractères
        $user->setBirthdate(new \DateTime('-20 years'));
        $user->setCin('12345678');

        $this->assertTrue($this->userManager->validate($user));
    }

    /**
     * Test: Utilisateur exactement 18 ans (limite).
     */
    public function testUserExactlyEighteenYearsOld(): void
    {
        $user = new User();
        $user->setPassword('password123');
        $user->setBirthdate(new \DateTime('-18 years'));
        $user->setCin('12345678');

        $this->assertTrue($this->userManager->validate($user));
    }

    /**
     * Test: Méthode isMajeur avec un utilisateur majeur.
     */
    public function testIsMajeurWithAdult(): void
    {
        $user = new User();
        $user->setBirthdate(new \DateTime('-25 years'));

        $this->assertTrue($this->userManager->isMajeur($user));
    }

    /**
     * Test: Méthode isMajeur avec un utilisateur mineur.
     */
    public function testIsMajeurWithMinor(): void
    {
        $user = new User();
        $user->setBirthdate(new \DateTime('-15 years'));

        $this->assertFalse($this->userManager->isMajeur($user));
    }

    /**
     * Test: Méthode isPasswordSecure avec mot de passe sécurisé.
     */
    public function testIsPasswordSecureWithValidPassword(): void
    {
        $user = new User();
        $user->setPassword('securepassword');

        $this->assertTrue($this->userManager->isPasswordSecure($user));
    }

    /**
     * Test: Méthode isPasswordSecure avec mot de passe non sécurisé.
     */
    public function testIsPasswordSecureWithShortPassword(): void
    {
        $user = new User();
        $user->setPassword('short');

        $this->assertFalse($this->userManager->isPasswordSecure($user));
    }

    /**
     * Test: Méthode isCinValid avec CIN valide.
     */
    public function testIsCinValidWithValidCin(): void
    {
        $user = new User();
        $user->setCin('87654321');

        $this->assertTrue($this->userManager->isCinValid($user));
    }

    /**
     * Test: Méthode isCinValid avec CIN invalide.
     */
    public function testIsCinValidWithInvalidCin(): void
    {
        $user = new User();
        $user->setCin('12345');

        $this->assertFalse($this->userManager->isCinValid($user));
    }
}
