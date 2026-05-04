<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\AuthService;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ObjectRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Tests unitaires pour le service AuthService.
 * 
 * Règles métier testées:
 * 1. L'email et le mot de passe sont obligatoires
 * 2. L'utilisateur doit exister dans la base
 * 3. Le compte doit être vérifié
 * 4. Le mot de passe doit correspondre (SHA256, plain, bcrypt)
 */
class AuthServiceTest extends TestCase
{
    private AuthService $authService;
    private EntityManagerInterface&MockObject $entityManager;
    private JWTTokenManagerInterface&MockObject $jwtManager;
    private EntityRepository&MockObject $userRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $this->userRepository = $this->createMock(EntityRepository::class);
        
        $this->authService = new AuthService($this->entityManager, $this->jwtManager);
    }

    /**
     * Crée un utilisateur avec les propriétés spécifiées.
     */
    private function createUser(string $email, string $password, bool $isVerified = true): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($password);
        $user->setIsVerified($isVerified);
        $user->setFirstname('Test');
        $user->setLastname('User');
        $user->setRole('USER');
        $user->setBirthdate(new \DateTime('-20 years'));
        $user->setCin('12345678');
        $user->setPhone_number(12345678);
        $user->setPoints(0);
        $user->setSolde('0');
        $user->setCreated_at(new \DateTime());
        $user->setNumero_carte('1234567890123456');
        
        return $user;
    }

    /**
     * Test: Login avec email vide.
     */
    public function testLoginWithEmptyEmail(): void
    {
        $result = $this->authService->login('', 'password123');
        
        $this->assertFalse($result['success']);
        $this->assertEquals(422, $result['code']);
        $this->assertEquals('Champs requis manquants.', $result['message']);
    }

    /**
     * Test: Login avec mot de passe vide.
     */
    public function testLoginWithEmptyPassword(): void
    {
        $result = $this->authService->login('test@example.com', '');
        
        $this->assertFalse($result['success']);
        $this->assertEquals(422, $result['code']);
        $this->assertEquals('Champs requis manquants.', $result['message']);
    }

    /**
     * Test: Login avec utilisateur non trouvé.
     */
    public function testLoginWithUserNotFound(): void
    {
        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'nonexistent@example.com'])
            ->willReturn(null);
        
        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($this->userRepository);
        
        $result = $this->authService->login('nonexistent@example.com', 'password123');
        
        $this->assertFalse($result['success']);
        $this->assertEquals(401, $result['code']);
        $this->assertEquals('Invalid credentials.', $result['message']);
    }

    /**
     * Test: Login avec compte non vérifié.
     */
    public function testLoginWithUnverifiedAccount(): void
    {
        $user = $this->createUser('unverified@example.com', 'password123', false);
        
        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($user);
        
        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($this->userRepository);
        
        $result = $this->authService->login('unverified@example.com', 'password123');
        
        $this->assertFalse($result['success']);
        $this->assertEquals(403, $result['code']);
        $this->assertStringContainsString('Compte non confirmé', $result['message']);
    }

    /**
     * Test: Login avec mot de passe incorrect.
     */
    public function testLoginWithWrongPassword(): void
    {
        $correctPasswordHash = hash('sha256', 'correctpassword');
        $user = $this->createUser('test@example.com', $correctPasswordHash, true);
        
        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($user);
        
        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($this->userRepository);
        
        $result = $this->authService->login('test@example.com', 'wrongpassword');
        
        $this->assertFalse($result['success']);
        $this->assertEquals(401, $result['code']);
        $this->assertEquals('Invalid credentials.', $result['message']);
    }

    /**
     * Test: Login réussi avec mot de passe correct (hash SHA256).
     */
    public function testLoginSuccessWithHashedPassword(): void
    {
        $password = 'password123';
        $passwordHash = hash('sha256', $password);
        $user = $this->createUser('test@example.com', $passwordHash, true);
        
        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($user);
        
        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($this->userRepository);
        
        $this->jwtManager->expects($this->once())
            ->method('create')
            ->with($user)
            ->willReturn('mock-jwt-token');
        
        $result = $this->authService->login('test@example.com', $password);
        
        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['code']);
        $this->assertEquals('mock-jwt-token', $result['token']);
        $this->assertArrayHasKey('roles', $result);
        $this->assertArrayHasKey('redirect_url', $result);
    }

    /**
     * Test: Login réussi avec mot de passe déjà hashé (format hex).
     */
    public function testLoginSuccessWithPreHashedPassword(): void
    {
        $passwordHash = hash('sha256', 'mypassword');
        $user = $this->createUser('test@example.com', $passwordHash, true);
        
        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($user);
        
        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($this->userRepository);
        
        $this->jwtManager->expects($this->once())
            ->method('create')
            ->willReturn('mock-jwt-token');
        
        $result = $this->authService->login('test@example.com', $passwordHash);
        
        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['code']);
    }

    /**
     * Test: Login avec utilisateur admin - redirection vers /admin.
     */
    public function testLoginAdminRedirectsToAdmin(): void
    {
        $password = 'adminpass123';
        $passwordHash = hash('sha256', $password);
        $user = $this->createUser('admin@example.com', $passwordHash, true);
        $user->setRole('ADMIN');
        
        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($user);
        
        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($this->userRepository);
        
        $this->jwtManager->expects($this->once())
            ->method('create')
            ->willReturn('admin-jwt-token');
        
        $result = $this->authService->login('admin@example.com', $password);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('/admin', $result['redirect_url']);
        $this->assertContains('ROLE_ADMIN', $result['roles']);
    }

    /**
     * Test: Login avec utilisateur normal - redirection vers /user/dashboard.
     */
    public function testLoginUserRedirectsToDashboard(): void
    {
        $password = 'userpass123';
        $passwordHash = hash('sha256', $password);
        $user = $this->createUser('user@example.com', $passwordHash, true);
        $user->setRole('USER');
        
        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($user);
        
        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($this->userRepository);
        
        $this->jwtManager->expects($this->once())
            ->method('create')
            ->willReturn('user-jwt-token');
        
        $result = $this->authService->login('user@example.com', $password);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('/user/dashboard', $result['redirect_url']);
        $this->assertContains('ROLE_USER', $result['roles']);
    }

    /**
     * Test: Vérification mot de passe - hash SHA256 correspondant.
     */
    public function testVerifyPasswordWithMatchingHash(): void
    {
        $password = 'mypassword';
        $hash = hash('sha256', $password);
        
        $this->assertTrue($this->authService->verifyPassword($password, $hash));
    }

    /**
     * Test: Vérification mot de passe - hash ne correspond pas.
     */
    public function testVerifyPasswordWithNonMatchingHash(): void
    {
        $password = 'mypassword';
        $wrongHash = hash('sha256', 'wrongpassword');
        
        $this->assertFalse($this->authService->verifyPassword($password, $wrongHash));
    }

    /**
     * Test: Vérification mot de passe - plain text correspondant.
     */
    public function testVerifyPasswordWithMatchingPlainText(): void
    {
        $password = 'mypassword';
        
        $this->assertTrue($this->authService->verifyPassword($password, $password));
    }

    /**
     * Test: Normalisation mot de passe - convertit en SHA256.
     */
    public function testNormalizePassword(): void
    {
        $password = 'mypassword';
        $expectedHash = strtolower(hash('sha256', $password));
        
        $this->assertEquals($expectedHash, $this->authService->normalizePassword($password));
    }

    /**
     * Test: Normalisation mot de passe - déjà un hash SHA256.
     */
    public function testNormalizePasswordAlreadyHashed(): void
    {
        $hash = strtoupper(hash('sha256', 'mypassword'));
        
        $this->assertEquals(strtolower($hash), $this->authService->normalizePassword($hash));
    }

    /**
     * Test: Validation email - email valide.
     */
    public function testIsValidEmailWithValidEmail(): void
    {
        $this->assertTrue($this->authService->isValidEmail('test@example.com'));
    }

    /**
     * Test: Validation email - email invalide.
     */
    public function testIsValidEmailWithInvalidEmail(): void
    {
        $this->assertFalse($this->authService->isValidEmail('invalid-email'));
    }

    /**
     * Test: Sécurité mot de passe - au moins 8 caractères.
     */
    public function testIsPasswordSecureWithValidPassword(): void
    {
        $this->assertTrue($this->authService->isPasswordSecure('password123'));
    }

    /**
     * Test: Sécurité mot de passe - moins de 8 caractères.
     */
    public function testIsPasswordSecureWithShortPassword(): void
    {
        $this->assertFalse($this->authService->isPasswordSecure('pass12'));
    }

    /**
     * Test: Sécurité mot de passe - exactement 8 caractères (limite).
     */
    public function testIsPasswordSecureWithExactlyEightCharacters(): void
    {
        $this->assertTrue($this->authService->isPasswordSecure('12345678'));
    }

    /**
     * Test: Hash mot de passe.
     */
    public function testHashPassword(): void
    {
        $password = 'mypassword';
        $hash = $this->authService->hashPassword($password);
        
        $this->assertEquals(64, strlen($hash));
        $this->assertTrue($this->authService->verifyPassword($password, $hash));
    }
}
