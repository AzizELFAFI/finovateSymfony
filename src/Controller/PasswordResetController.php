<?php

namespace App\Controller;

use App\Entity\PasswordResetRequest;
use App\Entity\User;
use App\Form\ForgotPasswordType;
use App\Form\ResetPasswordType;
use App\Repository\PasswordResetRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

final class PasswordResetController extends AbstractController
{
    private function toSha256Hex(string $input): string
    {
        return hash('sha256', $input);
    }

    private function randomToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    #[Route('/mot-de-passe-oublie', name: 'front_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer,
    ): Response {
        $form = $this->createForm(ForgotPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $emailInput = trim((string) ($data['email'] ?? ''));

            $user = $em->getRepository(User::class)->findOneBy(['email' => $emailInput]);
            if (!$user instanceof User) {
                $form->get('email')->addError(new \Symfony\Component\Form\FormError("Cet e-mail n'existe pas."));
            } else {
                $plainToken = $this->randomToken();
                $tokenHash = $this->toSha256Hex($plainToken);

                $reset = new PasswordResetRequest();
                $reset->setUser($user);
                $reset->setTokenHash($tokenHash);
                $reset->setCreatedAt(new \DateTime());
                $reset->setExpiresAt(new \DateTime('+1 hour'));

                $em->persist($reset);
                $em->flush();

                $link = $this->generateUrl('front_reset_password', ['token' => $plainToken], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);

                $message = (new Email())
                    ->from('no-reply@finovate.tn')
                    ->to($user->getEmail())
                    ->subject('Réinitialisation de votre mot de passe')
                    ->text("Bonjour,\n\nPour réinitialiser votre mot de passe, cliquez sur ce lien :\n{$link}\n\nCe lien expire dans 1 heure.\n");

                try {
                    $mailer->send($message);
                    $this->addFlash('success', 'Un e-mail de réinitialisation a été envoyé.');
                    return $this->redirectToRoute('front_login');
                } catch (TransportExceptionInterface $e) {
                    $this->addFlash('danger', "Impossible d'envoyer l'e-mail pour le moment.");
                }
            }
        }

        return $this->render('front/forgot_password.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/reinitialiser-mot-de-passe/{token}', name: 'front_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(
        string $token,
        Request $request,
        PasswordResetRequestRepository $resetRepo,
        EntityManagerInterface $em,
    ): Response {
        $token = trim($token);
        if ($token === '') {
            $this->addFlash('danger', 'Lien invalide.');
            return $this->redirectToRoute('front_login');
        }

        $tokenHash = $this->toSha256Hex($token);
        $reset = $resetRepo->findValidByTokenHash($tokenHash, new \DateTimeImmutable());

        if (!$reset instanceof PasswordResetRequest) {
            $this->addFlash('danger', 'Lien expiré ou invalide.');
            return $this->redirectToRoute('front_login');
        }

        $form = $this->createForm(ResetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $password = trim((string) ($data['password'] ?? ''));
            $confirm = trim((string) ($data['password_confirm'] ?? ''));

            if (!hash_equals($password, $confirm)) {
                $form->get('password_confirm')->addError(new \Symfony\Component\Form\FormError('Les mots de passe ne correspondent pas.'));
            } else {
                $user = $reset->getUser();
                if (!$user instanceof User) {
                    $this->addFlash('danger', 'Utilisateur introuvable.');
                    return $this->redirectToRoute('front_login');
                }

                $user->setPassword($this->toSha256Hex($password));
                $reset->setUsedAt(new \DateTime());

                $em->flush();

                $this->addFlash('success', 'Mot de passe réinitialisé avec succès.');
                return $this->redirectToRoute('front_login');
            }
        }

        return $this->render('front/reset_password.html.twig', [
            'form' => $form,
        ]);
    }
}
