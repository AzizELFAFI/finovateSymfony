<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

/**
 * Service métier pour l'authentification des utilisateurs.
 * 
 * Règles métier:
 * 1. L'email et le mot de passe sont obligatoires
 * 2. L'utilisateur doit exister dans la base
 * 3. Le compte doit être vérifié
 * 4. Le mot de passe doit correspondre
 */
class AuthService
{
    private EntityManagerInterface $entityManager;
    private JWTTokenManagerInterface $jwtManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        JWTTokenManagerInterface $jwtManager
    ) {
        $this->entityManager = $entityManager;
        $this->jwtManager = $jwtManager;
    }

    /**
     * Authentifie un utilisateur avec email et mot de passe.
     *
     * @param string $email Email de l'utilisateur
     * @param string $password Mot de passe (plain ou hashé SHA256)
     * @return array{success: true, token: string, roles: array, redirect_url: string}|array{success: false, message: string, code: int}
     */
    public function login(string $email, string $password): array
    {
        // Règle 1: Email et mot de passe obligatoires
        $email = trim($email);
        
        if ($email === '' || $password === '') {
            return [
                'success' => false,
                'message' => 'Champs requis manquants.',
                'code' => 422
            ];
        }

        // Règle 2: L'utilisateur doit exister
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        
        if (!$user instanceof User) {
            return [
                'success' => false,
                'message' => 'Invalid credentials.',
                'code' => 401
            ];
        }

        // Règle 3: Le compte doit être vérifié
        if (!$user->isVerified()) {
            return [
                'success' => false,
                'message' => 'Compte non confirmé. Veuillez vérifier votre e-mail.',
                'code' => 403
            ];
        }

        // Règle 4: Le mot de passe doit correspondre
        if (!$this->verifyPassword($password, $user->getPassword())) {
            return [
                'success' => false,
                'message' => 'Invalid credentials.',
                'code' => 401
            ];
        }

        // Générer le token JWT
        $token = $this->jwtManager->create($user);
        $roles = $user->getRoles();
        $redirectUrl = in_array('ROLE_ADMIN', $roles, true) ? '/admin' : '/user/dashboard';

        return [
            'success' => true,
            'token' => $token,
            'roles' => $roles,
            'redirect_url' => $redirectUrl,
            'code' => 200
        ];
    }

    /**
     * Vérifie si le mot de passe correspond au hash stocké.
     * Supporte: SHA256 hash, plain text, et bcrypt.
     *
     * @param string $incomingPassword Mot de passe entrant
     * @param string $storedPassword Mot de passe stocké
     * @return bool True si le mot de passe correspond
     */
    public function verifyPassword(string $incomingPassword, string $storedPassword): bool
    {
        $incomingHash = $this->normalizePassword($incomingPassword);
        $storedHash = strtolower($storedPassword);

        // Comparaison des hashes SHA256
        if (hash_equals($storedHash, $incomingHash)) {
            return true;
        }

        // Comparaison directe (plain text)
        if ($storedPassword === $incomingPassword) {
            return true;
        }

        // Vérification bcrypt
        if (password_verify($incomingPassword, $storedPassword)) {
            return true;
        }

        return false;
    }

    /**
     * Normalise un mot de passe en hash SHA256.
     *
     * @param string $password Mot de passe à normaliser
     * @return string Hash SHA256 en minuscules
     */
    public function normalizePassword(string $password): string
    {
        $password = trim($password);

        // Si c'est déjà un hash SHA256 (64 caractères hex)
        if (preg_match('/^[a-f0-9]{64}$/i', $password)) {
            return strtolower($password);
        }

        return strtolower(hash('sha256', $password));
    }

    /**
     * Vérifie si un email est valide.
     *
     * @param string $email Email à vérifier
     * @return bool True si l'email est valide
     */
    public function isValidEmail(string $email): bool
    {
        return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Vérifie si un mot de passe respecte les règles de sécurité.
     * Règle: Au moins 8 caractères.
     *
     * @param string $password Mot de passe à vérifier
     * @return bool True si le mot de passe est sécurisé
     */
    public function isPasswordSecure(string $password): bool
    {
        return mb_strlen($password) >= 8;
    }

    /**
     * Génère un hash SHA256 pour un mot de passe.
     *
     * @param string $password Mot de passe en clair
     * @return string Hash SHA256
     */
    public function hashPassword(string $password): string
    {
        return $this->normalizePassword($password);
    }
}
