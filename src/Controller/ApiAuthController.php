<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\TransactionRepository;
use App\Service\EmailVerifier;
use App\Service\AiService;
use App\Service\FaceApiClient;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use ReCaptcha\ReCaptcha;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
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

    #[Route('/api/ai/signup-field', name: 'api_ai_signup_field', methods: ['POST'])]
    public function aiSignupField(Request $request, AiService $aiService): JsonResponse
    {
        $payload = json_decode($request->getContent() ?: '', true);
        if (!is_array($payload)) {
            return $this->json(['message' => 'Payload JSON invalide.'], 400);
        }

        $field = trim((string) ($payload['field'] ?? ''));
        $transcript = trim((string) ($payload['transcript'] ?? ''));

        if ($field === '' || $transcript === '') {
            return $this->json(['message' => 'Champs requis manquants.'], 422);
        }

        try {
            $value = $aiService->extractSignupField($field, $transcript);
        } catch (\Throwable $e) {
            $msg = 'IA indisponible.';
            if ($this->getParameter('kernel.environment') === 'dev') {
                $msg = $e->getMessage();
            }
            return $this->json(['message' => $msg], 502);
        }

        return $this->json([
            'value' => $value,
        ]);
    }

    #[Route('/api/ai/signup-all', name: 'api_ai_signup_all', methods: ['POST'])]
    public function aiSignupAll(Request $request, AiService $aiService): JsonResponse
    {
        $payload = json_decode($request->getContent() ?: '', true);
        if (!is_array($payload)) {
            return $this->json(['message' => 'Payload JSON invalide.'], 400);
        }

        $transcript = trim((string) ($payload['transcript'] ?? ''));

        if ($transcript === '') {
            return $this->json(['message' => 'Transcription manquante.'], 422);
        }

        try {
            $fields = $aiService->extractAllSignupFields($transcript);
        } catch (\Throwable $e) {
            $msg = 'IA indisponible.';
            if ($this->getParameter('kernel.environment') === 'dev') {
                $msg = $e->getMessage();
            }
            return $this->json(['message' => $msg], 502);
        }

        return $this->json($fields);
    }

    #[Route('/api/face/disable', name: 'api_face_disable', methods: ['POST'])]
    public function disableFace(Security $security, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié.'], 401);
        }

        $user->setFaceAuthEnabled(false);
        $user->setFaceEmbedding(null);
        $user->setUpdatedAt(new \DateTime());

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
            'message' => 'Face ID désactivé.',
            'face_enabled' => false,
        ]);
    }

    #[Route('/api/face/enroll', name: 'api_face_enroll', methods: ['POST'])]
    public function enrollFace(Request $request, Security $security, EntityManagerInterface $entityManager, FaceApiClient $faceApiClient): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié.'], 401);
        }

        $file = $request->files->get('image');
        if (!$file instanceof UploadedFile) {
            return $this->json(['message' => 'Fichier image manquant (champ "image").'], 422);
        }

        if (!$file->isValid()) {
            return $this->json(['message' => 'Upload invalide.'], 422);
        }

        $path = $file->getPathname();
        if (!file_exists($path)) {
            error_log("enrollFace - File not found: " . $path);
            return $this->json(['message' => 'Fichier temporaire introuvable.'], 500);
        }

        $bytes = @file_get_contents($path);
        if ($bytes === false || $bytes === '') {
            error_log("enrollFace - Failed to read file bytes: " . $path);
            return $this->json(['message' => 'Impossible de lire l\'image.'], 422);
        }

        try {
            error_log("enrollFace - Calling FaceApiClient::enroll");
            $result = $faceApiClient->enroll('user-' . $user->getId(), $bytes);
            error_log("enrollFace - FaceApiClient::enroll success");
            $embedding = $result['embedding'] ?? null;
            if (!is_array($embedding)) {
                return $this->json(['message' => 'Réponse API visage invalide.'], 422);
            }

            $user->setFaceEmbedding(json_encode($embedding, JSON_THROW_ON_ERROR));
            $user->setFaceAuthEnabled(true);
            $user->setUpdatedAt(new \DateTime());
            $entityManager->flush();
        } catch (\RuntimeException $e) {
            // Extract detail from FastAPI error message
            $msg = $e->getMessage();
            return $this->json([
                'message' => $msg,
            ], 422);
        } catch (\Throwable $e) {
            return $this->json([
                'message' => 'Enrôlement visage impossible: ' . $e->getMessage(),
            ], 422);
        }

        return $this->json([
            'message' => 'Visage enregistré. La connexion 2FA par visage est activée.',
            'face_enabled' => true,
        ]);
    }

    #[Route('/api/login-face-only', name: 'api_login_face_only', methods: ['POST'])]
    public function loginFaceOnly(Request $request, EntityManagerInterface $entityManager, JWTTokenManagerInterface $jwtManager, FaceApiClient $faceApiClient): JsonResponse
    {
        $email = trim((string) $request->request->get('email', ''));

        if ($email === '') {
            return $this->json(['message' => 'Champs requis manquants.'], 422);
        }

        $file = $request->files->get('image');
        if (!$file instanceof UploadedFile) {
            return $this->json(['message' => 'Fichier image manquant (champ "image").'], 422);
        }

        if (!$file->isValid()) {
            return $this->json(['message' => 'Upload invalide.'], 422);
        }

        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            return $this->json(['message' => 'Invalid credentials.'], 401);
        }

        if (!$user->isVerified()) {
            return $this->json(['message' => 'Compte non confirmé. Veuillez vérifier votre e-mail.'], 403);
        }

        if (!$user->isFaceAuthEnabled() || !$user->getFaceEmbedding()) {
            return $this->json(['message' => 'Authentification par visage non activée pour ce compte.'], 403);
        }

        $bytes = @file_get_contents($file->getPathname());
        if (!is_string($bytes) || $bytes === '') {
            return $this->json(['message' => 'Impossible de lire l\'image.'], 422);
        }

        try {
            $verify = $faceApiClient->verify($bytes, $user->getFaceEmbedding(), 0.35);
            $match = (bool) ($verify['match'] ?? false);
        } catch (\Throwable $e) {
            $msg = 'Vérification visage impossible.';
            if ($this->getParameter('kernel.environment') === 'dev') {
                $msg = $e->getMessage();
            }
            return $this->json(['message' => $msg], 422);
        }

        if (!$match) {
            return $this->json(['message' => 'Visage non reconnu.'], 401);
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

        if (!$user->isVerified()) {
            return $this->json(['message' => 'Compte non confirmé. Veuillez vérifier votre e-mail.'], 403);
        }

        $incomingHash = $this->normalizeIncomingPassword($password);
        $storedPassword = (string) $user->getPassword();
        $storedHash = strtolower($storedPassword);

        $isValid = false;
        if (hash_equals($storedHash, $incomingHash)) {
            $isValid = true;
        } elseif ($storedPassword === $password) {
            $isValid = true;
        } elseif (password_verify($password, $storedPassword)) {
            $isValid = true;
        }

        if (!$isValid) {
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
        ValidatorInterface $validator,
        ReCaptcha $reCaptcha,
        EmailVerifier $emailVerifier
    ): JsonResponse {
        $payload = json_decode($request->getContent() ?: '', true);

        if (!is_array($payload)) {
            return $this->json(['message' => 'Payload JSON invalide.'], 400);
        }

        // Validate reCAPTCHA token
        $recaptchaToken = (string) ($payload['recaptcha_token'] ?? '');
        if ($recaptchaToken === '') {
            return $this->json(['message' => 'Veuillez confirmer que vous n\'êtes pas un robot.'], 422);
        }

        $recaptchaResult = $reCaptcha->verify($recaptchaToken, $request->getClientIp());
        if (!$recaptchaResult->isSuccess()) {
            return $this->json(['message' => 'Validation reCAPTCHA échouée. Veuillez réessayer.'], 422);
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
        $user->setIsVerified(false);

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

        $fromEmail = (string) $this->getParameter('mailer_from_email');
        $fromName = (string) $this->getParameter('mailer_from_name');

        $emailMessage = (new TemplatedEmail())
            ->from(new Address($fromEmail, $fromName))
            ->to($user->getEmail())
            ->subject('Confirmez votre compte Finovate')
            ->htmlTemplate('emails/verify_email.html.twig')
            ->context([]);

        try {
            $emailVerifier->sendEmailConfirmation('app_verify_email', $user, $emailMessage);
        } catch (\Throwable) {
            // If email fails, keep user unverified
        }

        return $this->json(['message' => 'Compte créé avec succès. Veuillez confirmer votre e-mail.'], 201);
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(Security $security, TransactionRepository $transactionRepo): JsonResponse
    {
        $user = $security->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié.'], 401);
        }

        $imageUrl = null;
        if ($user->getImageName()) {
            $imageUrl = '/uploads/profile/' . $user->getImageName();
        }

        // Get transaction statistics
        $transactionStats = $transactionRepo->getUserStats((int) $user->getId());

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
            'image_url' => $imageUrl,
            'face_enabled' => $user->isFaceAuthEnabled(),
            'transaction_stats' => $transactionStats,
        ]);
    }

    #[Route('/api/me/avatar', name: 'api_me_avatar', methods: ['POST'])]
    public function uploadAvatar(Request $request, Security $security, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié.'], 401);
        }

        $file = $request->files->get('image');
        if (!$file instanceof UploadedFile) {
            return $this->json(['message' => 'Fichier image manquant (champ "image").'], 422);
        }

        if (!$file->isValid()) {
            return $this->json(['message' => 'Upload invalide.'], 422);
        }

        $mime = (string) $file->getMimeType();
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return $this->json(['message' => 'Format image non supporté. Utilisez JPG/PNG/WebP.'], 422);
        }

        if ($file->getSize() !== null && $file->getSize() > 2 * 1024 * 1024) {
            return $this->json(['message' => 'Image trop grande (max 2MB).'], 422);
        }

        $user->setImageFile($file);

        try {
            $entityManager->flush();
        } catch (\Throwable $e) {
            $msg = 'Mise à jour impossible.';
            if ($this->getParameter('kernel.environment') === 'dev') {
                $msg = $e->getMessage();
            }
            return $this->json(['message' => $msg], 500);
        }

        $imageUrl = null;
        if ($user->getImageName()) {
            $imageUrl = '/uploads/profile/' . $user->getImageName();
        }

        return $this->json([
            'message' => 'Image mise à jour.',
            'image_url' => $imageUrl,
        ]);
    }

    #[Route('/api/me/avatar', name: 'api_me_avatar_delete', methods: ['DELETE'])]
    public function deleteAvatar(Security $security, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié.'], 401);
        }

        $user->setImageName(null);
        $user->setUpdatedAt(new \DateTime());

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
            'message' => 'Photo supprimée.',
            'image_url' => null,
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

    #[Route('/api/ai/financial-advice', name: 'api_ai_financial_advice', methods: ['POST'])]
    public function generateFinancialAdvice(
        Request $request,
        AiService $aiService,
        TransactionRepository $transactionRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié.'], 401);
        }

        // Get transaction stats
        $txStats = $transactionRepository->getUserStats((int) $user->getId());

        // Get goals stats
        $goals = $entityManager->getRepository(\App\Entity\Goal::class)->findBy(['id_user' => (int) $user->getId()]);
        $goalsCount = count($goals);
        $goalsInProgress = 0;
        $goalsCompleted = 0;
        $goalsTotalSaved = 0;

        foreach ($goals as $goal) {
            if ($goal->getStatus() === 'COMPLETED') {
                $goalsCompleted++;
            } else {
                $goalsInProgress++;
            }
            $goalsTotalSaved += (float) str_replace(',', '.', (string) $goal->getCurrent_amount());
        }

        $userData = [
            'solde' => (float) str_replace(',', '.', (string) $user->getSolde()),
            'points' => (int) $user->getPoints(),
            'sent_today' => (float) ($txStats['sent_today'] ?? 0),
            'sent_this_month' => (float) ($txStats['sent_this_month'] ?? 0),
            'total_received' => (float) ($txStats['total_received'] ?? 0),
            'sent_count' => (int) ($txStats['sent_count'] ?? 0),
            'received_count' => (int) ($txStats['received_count'] ?? 0),
            'daily_remaining' => (float) ($txStats['daily_remaining'] ?? 3000),
            'goals_count' => $goalsCount,
            'goals_in_progress' => $goalsInProgress,
            'goals_completed' => $goalsCompleted,
            'goals_total_saved' => $goalsTotalSaved,
        ];

        try {
            $advice = $aiService->generateFinancialAdvice($userData);
            return $this->json(['advice' => $advice]);
        } catch (\Throwable $e) {
            return $this->json([
                'message' => 'Erreur lors de la génération du conseil.',
                'advice' => 'Désolé, je ne peux pas générer de conseil pour le moment. Veuillez réessayer plus tard.'
            ], 500);
        }
    }
}
