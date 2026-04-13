<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class GithubAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $entityManager,
        private RouterInterface $router,
        private JWTTokenManagerInterface $jwtManager,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_github_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('github');
        $accessToken = $this->fetchAccessToken($client);

        $session = $request->getSession();

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client, $session) {
                /** @var \League\OAuth2\Client\Provider\GithubResourceOwner $githubUser */
                $githubUser = $client->fetchUserFromToken($accessToken);

                $email = (string) $githubUser->getEmail();
                $email = trim($email);

                // GitHub peut ne pas retourner l'email public, on essaie de le récupérer via l'API
                if ($email === '') {
                    $email = $this->fetchPrimaryEmail($client, $accessToken);
                }

                if ($email === '') {
                    throw new CustomUserMessageAuthenticationException('GITHUB_NO_EMAIL');
                }

                $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

                if ($user instanceof User) {
                    return $user;
                }

                // Pas de création ici: User a des contraintes NotBlank (firstname, lastname, etc.)
                // On passe par la page "compléter profil".
                $session->set('github_oauth_email', $email);
                $session->set('github_oauth_nickname', $githubUser->getNickname() ?: null);
                $githubData = $githubUser->toArray();
                $session->set('github_oauth_picture', $githubData['avatar_url'] ?? null);

                // On empêche la création d'un token authentifié (sinon Symfony tente de sérialiser l'utilisateur en session).
                // La redirection vers la page de complétion se fait dans onAuthenticationFailure().
                throw new CustomUserMessageAuthenticationException('GITHUB_PROFILE_INCOMPLETE');
            })
        );
    }

    /**
     * Récupère l'email principal de l'utilisateur GitHub via l'API.
     */
    private function fetchPrimaryEmail($client, $accessToken): string
    {
        try {
            $response = $client->getHttpClient()->request('GET', 'https://api.github.com/user/emails', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken->getToken(),
                    'Accept' => 'application/vnd.github.v3+json',
                ],
            ]);

            $emails = json_decode($response->getBody()->getContents(), true);

            if (is_array($emails)) {
                foreach ($emails as $emailData) {
                    if (isset($emailData['primary']) && $emailData['primary'] === true && isset($emailData['verified']) && $emailData['verified'] === true) {
                        return (string) ($emailData['email'] ?? '');
                    }
                }
            }
        } catch (\Throwable) {
            // Ignore les erreurs
        }

        return '';
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return new RedirectResponse($this->router->generate('front_login'));
        }

        $jwt = $this->jwtManager->create($user);
        $url = $this->router->generate('oauth_success', [
            'token' => $jwt,
            'redirect' => $this->router->generate('user_dashboard'),
        ]);

        return new RedirectResponse($url);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if ($exception instanceof CustomUserMessageAuthenticationException) {
            $messageKey = $exception->getMessageKey();

            if ($messageKey === 'GITHUB_PROFILE_INCOMPLETE') {
                return new RedirectResponse($this->router->generate('github_complete_profile'));
            }

            if ($messageKey === 'GITHUB_NO_EMAIL') {
                $request->getSession()->getFlashBag()->add('error', 'Impossible de récupérer votre adresse email depuis GitHub. Vérifiez vos paramètres GitHub.');
                return new RedirectResponse($this->router->generate('front_login'));
            }
        }

        return new RedirectResponse($this->router->generate('front_login'));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->router->generate('front_login'));
    }
}
