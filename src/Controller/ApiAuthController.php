<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ApiAuthController extends AbstractController
{
    private function toSha256Hex(string $input): string
    {
        return hash('sha256', $input);
    }

    private function normalizeIncomingPassword(string $password): string
    {
        $password = trim($password);

        if (preg_match('/^[a-f0-9]{64}$/i', $password)) {
            return strtolower($password);
        }

        return $this->toSha256Hex($password);
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request, EntityManagerInterface $entityManager, JWTTokenManagerInterface $jwtManager): JsonResponse
    {
        $payload = json_decode($request->getContent() ?: '', true);

        if (!is_array($payload)) {
            return $this->json(['message' => 'Payload JSON invalide.'], 400);
        }

        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ($email === '' || $password === '') {
            return $this->json(['message' => 'Champs requis manquants.'], 422);
        }

        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            return $this->json(['message' => 'Invalid credentials.'], 401);
        }

        $incomingHash = $this->normalizeIncomingPassword($password);
        $storedHash = strtolower($user->getPassword());

        if (!hash_equals($storedHash, $incomingHash)) {
            return $this->json(['message' => 'Invalid credentials.'], 401);
        }

        $token = $jwtManager->create($user);

        $roles = $user->getRoles();
        $redirectUrl = in_array('ROLE_ADMIN', $roles, true) ? '/admin' : '/user/dashboard';

        $response = $this->json([
            'token' => $token,
            'roles' => $roles,
            'redirect_url' => $redirectUrl,
        ]);

        $response->headers->setCookie(Cookie::create('finovate_token')
            ->withValue($token)
            ->withHttpOnly(true)
            ->withSecure(false)
            ->withSameSite('lax')
            ->withPath('/')
        );

        return $response;
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): JsonResponse {
        $payload = json_decode($request->getContent() ?: '', true);

        if (!is_array($payload)) {
            return $this->json(['message' => 'Payload JSON invalide.'], 400);
        }

        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        $passwordConfirm = (string) ($payload['password_confirm'] ?? '');
        $nom = trim((string) ($payload['nom'] ?? ''));
        $prenom = trim((string) ($payload['prenom'] ?? ''));
        $dateNaissance = (string) ($payload['date_naissance'] ?? '');
        $cin = trim((string) ($payload['cin'] ?? ''));
        $telephone = (string) ($payload['telephone'] ?? '');

        if ($email === '' || $password === '' || $nom === '' || $prenom === '' || $dateNaissance === '' || $cin === '' || $telephone === '') {
            return $this->json(['message' => 'Champs requis manquants.'], 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'Adresse e-mail invalide.'], 422);
        }

        if (mb_strlen($nom) < 3) {
            return $this->json(['message' => 'Le nom doit contenir au moins 3 caractères.'], 422);
        }

        if (mb_strlen($prenom) < 3) {
            return $this->json(['message' => 'Le prénom doit contenir au moins 3 caractères.'], 422);
        }

        if (!preg_match('/^\d{8}$/', $cin)) {
            return $this->json(['message' => 'Le CIN doit contenir exactement 8 chiffres.'], 422);
        }

        $telephoneDigits = preg_replace('/\D+/', '', $telephone);
        if (!is_string($telephoneDigits)) {
            $telephoneDigits = '';
        }

        if (!preg_match('/^\d{8}$/', $telephoneDigits)) {
            return $this->json(['message' => 'Le numéro de téléphone doit contenir exactement 8 chiffres.'], 422);
        }

        if (mb_strlen($password) < 8) {
            return $this->json(['message' => 'Le mot de passe doit contenir au moins 8 caractères.'], 422);
        }

        if (mb_strlen($passwordConfirm) < 8) {
            return $this->json(['message' => 'La confirmation du mot de passe doit contenir au moins 8 caractères.'], 422);
        }

        if (!hash_equals($password, $passwordConfirm)) {
            return $this->json(['message' => 'Les mots de passe ne correspondent pas.'], 422);
        }

        $passwordHash = $this->normalizeIncomingPassword($password);

        $existing = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            return $this->json(['message' => 'Cet e-mail est déjà utilisé.'], 409);
        }

        try {
            $birthdate = new \DateTime($dateNaissance);
        } catch (\Throwable) {
            return $this->json(['message' => 'Date de naissance invalide.'], 422);
        }

        $today = new \DateTimeImmutable('today');
        $birthImmutable = \DateTimeImmutable::createFromMutable($birthdate)->setTime(0, 0, 0);
        $age = $birthImmutable->diff($today)->y;
        if ($age < 18) {
            return $this->json(['message' => 'Vous devez avoir au moins 18 ans pour créer un compte.'], 422);
        }

        $user = new User();
        $user->setId((string) (int) (microtime(true) * 1000));
        $user->setEmail($email);
        $user->setFirstname($prenom);
        $user->setLastname($nom);
        $user->setRole('USER');
        $user->setPoints(0);
        $user->setSolde('0');
        $user->setBirthdate($birthdate);
        $user->setCin($cin);
        $user->setPhone_number((int) $telephoneDigits);
        $user->setCreated_at(new \DateTime());
        $user->setNumero_carte((string) random_int(1000000000000000, 9999999999999999));
        $user->setPassword($passwordHash);

        $violations = $validator->validate($user);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $property = (string) $violation->getPropertyPath();
                $errors[$property][] = (string) $violation->getMessage();
            }

            return $this->json([
                'message' => 'Données invalides.',
                'errors' => $errors,
            ], 422);
        }

        $entityManager->persist($user);
        try {
            $entityManager->flush();
        } catch (\Throwable $e) {
            $msg = 'Mise à jour impossible.';
            if ($this->getParameter('kernel.environment') === 'dev') {
                $msg = $e->getMessage();
            }
            return $this->json(['message' => $msg], 500);
        }

        return $this->json(['message' => 'Compte créé avec succès.'], 201);
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(Security $security): JsonResponse
    {
        $user = $security->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié.'], 401);
        }

        return $this->json([
            'nom' => $user->getLastname(),
            'prenom' => $user->getFirstname(),
            'email' => $user->getEmail(),
            'telephone' => $user->getPhone_number(),
            'date_naissance' => $user->getBirthdate()->format('Y-m-d'),
            'cin' => $user->getCin(),
            'roles' => $user->getRoles(),
            'solde' => $user->getSolde(),
            'points' => $user->getPoints(),
            'numero_carte' => $user->getNumero_carte(),
        ]);
    }

    #[Route('/api/me', name: 'api_me_update', methods: ['PUT'])]
    public function updateMe(Request $request, Security $security, EntityManagerInterface $entityManager, ValidatorInterface $validator): JsonResponse
    {
        $user = $security->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié.'], 401);
        }

        $payload = json_decode($request->getContent() ?: '', true);
        if (!is_array($payload)) {
            return $this->json(['message' => 'Payload JSON invalide.'], 400);
        }

        $nom = array_key_exists('nom', $payload) ? trim((string) $payload['nom']) : null;
        $prenom = array_key_exists('prenom', $payload) ? trim((string) $payload['prenom']) : null;
        $email = array_key_exists('email', $payload) ? trim((string) $payload['email']) : null;
        $password = array_key_exists('password', $payload) ? (string) $payload['password'] : null;
        $passwordConfirm = array_key_exists('password_confirm', $payload) ? (string) $payload['password_confirm'] : null;
        $dateNaissance = array_key_exists('date_naissance', $payload) ? trim((string) $payload['date_naissance']) : null;
        $cin = array_key_exists('cin', $payload) ? trim((string) $payload['cin']) : null;
        $telephone = array_key_exists('telephone', $payload) ? (string) $payload['telephone'] : null;

        if ($password !== null) {
            $password = trim($password);
            if ($password === '') {
                $password = null;
            }
        }

        if ($passwordConfirm !== null) {
            $passwordConfirm = trim($passwordConfirm);
            if ($passwordConfirm === '') {
                $passwordConfirm = null;
            }
        }

        if ($nom !== null) {
            if ($nom === '') {
                return $this->json(['message' => 'Nom invalide.'], 422);
            }
            $user->setLastname($nom);
        }

        if ($prenom !== null) {
            if ($prenom === '') {
                return $this->json(['message' => 'Prénom invalide.'], 422);
            }
            $user->setFirstname($prenom);
        }

        if ($email !== null) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->json(['message' => 'Adresse e-mail invalide.'], 422);
            }

            if (strtolower($email) !== strtolower($user->getEmail())) {
                $existing = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
                if ($existing instanceof User) {
                    return $this->json(['message' => 'Cet e-mail est déjà utilisé.'], 409);
                }
            }

            $user->setEmail($email);
        }

        if ($password !== null) {
            if ($passwordConfirm === null || $passwordConfirm === '') {
                return $this->json(['message' => 'Confirmation du mot de passe obligatoire.'], 422);
            }

            if ($password !== $passwordConfirm) {
                return $this->json(['message' => 'Les mots de passe ne correspondent pas.'], 422);
            }

            $user->setPassword($this->normalizeIncomingPassword($password));
        }

        if ($passwordConfirm !== null && $password === null) {
            return $this->json(['message' => 'Confirmation du mot de passe invalide.'], 422);
        }

        if ($dateNaissance !== null) {
            if ($dateNaissance === '') {
                return $this->json(['message' => 'Date de naissance invalide.'], 422);
            }
            try {
                $birthdate = new \DateTime($dateNaissance);
            } catch (\Throwable) {
                return $this->json(['message' => 'Date de naissance invalide.'], 422);
            }

            $today = new \DateTimeImmutable('today');
            $birthImmutable = \DateTimeImmutable::createFromMutable($birthdate)->setTime(0, 0, 0);
            $age = $birthImmutable->diff($today)->y;
            if ($age < 18) {
                return $this->json(['message' => 'Vous devez avoir au moins 18 ans pour créer un compte.'], 422);
            }

            $user->setBirthdate($birthdate);
        }

        if ($cin !== null) {
            if ($cin === '') {
                return $this->json(['message' => 'CIN invalide.'], 422);
            }
            if (!preg_match('/^\d{8}$/', $cin)) {
                return $this->json(['message' => 'Le CIN doit contenir exactement 8 chiffres.'], 422);
            }
            $user->setCin($cin);
        }

        if ($telephone !== null) {
            $digits = preg_replace('/\D+/', '', $telephone);
            if ($digits === '') {
                return $this->json(['message' => 'Téléphone invalide.'], 422);
            }
            if (!preg_match('/^\d{8}$/', $digits)) {
                return $this->json(['message' => 'Le numéro de téléphone doit contenir exactement 8 chiffres.'], 422);
            }
            $user->setPhone_number((int) $digits);
        }

        $violations = $validator->validate($user);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $property = (string) $violation->getPropertyPath();
                $errors[$property][] = (string) $violation->getMessage();
            }

            return $this->json([
                'message' => 'Données invalides.',
                'errors' => $errors,
            ], 422);
        }

        try {
            $entityManager->flush();
        } catch (\Throwable $e) {
            $msg = 'Mise à jour impossible.';
            if ($this->getParameter('kernel.environment') === 'dev') {
                $msg = $e->getMessage();
            }
            return $this->json(['message' => $msg], 500);
        }

        return $this->json([
            'message' => 'Profil mis à jour.',
            'nom' => $user->getLastname(),
            'prenom' => $user->getFirstname(),
            'email' => $user->getEmail(),
            'telephone' => $user->getPhone_number(),
            'date_naissance' => $user->getBirthdate()->format('Y-m-d'),
            'cin' => $user->getCin(),
        ]);
    }

    #[Route('/api/dev/password-check', name: 'api_dev_password_check', methods: ['POST'])]
    public function devPasswordCheck(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        if ($this->getParameter('kernel.environment') !== 'dev') {
            return $this->json(['message' => 'Not found.'], 404);
        }

        $payload = json_decode($request->getContent() ?: '', true);
        if (!is_array($payload)) {
            return $this->json(['message' => 'Payload JSON invalide.'], 400);
        }

        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            return $this->json(['found' => false], 200);
        }

        return $this->json([
            'found' => true,
            'password_valid' => $passwordHasher->isPasswordValid($user, $password),
            'stored_password' => $user->getPassword(),
        ]);
    }
}
