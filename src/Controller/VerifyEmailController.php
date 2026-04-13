<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

final class VerifyEmailController extends AbstractController
{
    #[Route('/verify/email', name: 'app_verify_email', methods: ['GET'])]
    public function verifyUserEmail(Request $request, EntityManagerInterface $entityManager, EmailVerifier $emailVerifier): Response
    {
        $id = (string) $request->query->get('id', '');
        if ($id === '') {
            return new Response('Lien invalide.', 400);
        }

        $user = $entityManager->getRepository(User::class)->find($id);
        if (!$user instanceof User) {
            return new Response('Utilisateur introuvable.', 404);
        }

        try {
            $emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $e) {
            return new Response('Lien expiré ou invalide.', 400);
        }

        return new Response('Votre compte a été confirmé avec succès. Vous pouvez vous connecter.', 200);
    }
}
