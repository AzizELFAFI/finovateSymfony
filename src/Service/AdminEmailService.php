<?php

namespace App\Service;

use App\Dto\RestrictionDto;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AdminEmailService
{
    private const BREVO_URL = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(
        private HttpClientInterface $http,
        private LoggerInterface     $logger,
        private string $brevoApiKey,
        private string $fromEmail = 'noreply@finovate.com',
        private string $fromName  = 'FINOVATE',
    ) {}

    public function sendContentRemovedEmail(User $user, string $reason, string $contentTitle): void
    {
        $this->send(
            $user->getEmail(),
            $user->getFirstname(),
            '[FINOVATE] Votre contenu a été supprimé',
            $this->buildContentRemovedBody($user, $reason, $contentTitle)
        );
    }

    public function sendWarningEmail(User $user, string $reason): void
    {
        $this->send(
            $user->getEmail(),
            $user->getFirstname(),
            '[FINOVATE] Avertissement — Infraction détectée',
            $this->buildWarningBody($user, $reason)
        );
    }

    public function sendRestrictionEmail(User $user, RestrictionDto $dto): void
    {
        $this->send(
            $user->getEmail(),
            $user->getFirstname(),
            '[FINOVATE] Votre compte a été restreint',
            $this->buildRestrictionBody($user, $dto)
        );
    }

    public function sendBanEmail(User $user, string $reason): void
    {
        $this->send(
            $user->getEmail(),
            $user->getFirstname(),
            '[FINOVATE] Votre compte a été banni',
            $this->buildBanBody($user, $reason)
        );
    }

    private function send(string $toEmail, string $toName, string $subject, string $htmlBody): void
    {
        try {
            $this->http->request('POST', self::BREVO_URL, [
                'headers' => [
                    'api-key'      => $this->brevoApiKey,
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'json' => [
                    'sender'     => ['name' => $this->fromName, 'email' => $this->fromEmail],
                    'to'         => [['email' => $toEmail, 'name' => $toName]],
                    'subject'    => $subject,
                    'htmlContent'=> $htmlBody,
                ],
            ])->getStatusCode(); // trigger the request
        } catch (\Throwable $e) {
            $this->logger->warning('AdminEmailService: failed to send email to ' . $toEmail . ': ' . $e->getMessage());
        }
    }

    private function buildContentRemovedBody(User $user, string $reason, string $contentTitle): string
    {
        $specificMessage = match(true) {
            str_contains($reason, 'Misinformation') || str_contains($reason, 'Fausses') =>
                "Votre contenu contenait des <strong>informations financières non vérifiées ou trompeuses</strong>. Sur FINOVATE, nous exigeons que toute information partagée soit sourcée et vérifiable. La désinformation financière peut causer des préjudices réels à notre communauté.",
            str_contains($reason, 'toxique') || str_contains($reason, 'offensant') || str_contains($reason, 'haineux') =>
                "Votre contenu a été jugé <strong>offensant, toxique ou haineux</strong>. FINOVATE est un espace de respect mutuel. Tout contenu discriminatoire, harcelant ou insultant est strictement interdit.",
            str_contains($reason, 'Spam') || str_contains($reason, 'répétitif') =>
                "Votre contenu a été identifié comme <strong>spam ou contenu répétitif</strong>. Chaque publication doit apporter une valeur réelle à la communauté. Les publications répétitives ou hors-sujet ne sont pas tolérées.",
            str_contains($reason, 'règles') || str_contains($reason, 'communauté') =>
                "Votre contenu a violé les <strong>règles de notre communauté</strong>. Nous vous invitons à relire notre politique pour comprendre les comportements attendus sur FINOVATE.",
            str_contains($reason, 'financier') || str_contains($reason, 'trompeur') =>
                "Votre contenu contenait des <strong>informations financières trompeuses</strong> pouvant induire les membres en erreur. FINOVATE a une responsabilité envers ses membres de maintenir un contenu financier fiable.",
            default =>
                "Votre contenu ne respectait pas les standards de notre communauté.",
        };

        $offenseWarning = "
        <div style='background:#fff3cd;border-left:4px solid #f59e0b;padding:12px 16px;margin:16px 0;border-radius:4px'>
            <strong style='color:#92400e'>⚠️ Rappel de la politique :</strong>
            <ul style='color:#555;margin:8px 0 0 16px;line-height:1.8'>
                <li>1ère infraction : Avertissement par email</li>
                <li>2ème infraction : Restriction temporaire du compte</li>
                <li>3ème infraction : Bannissement définitif</li>
            </ul>
        </div>";

        return "
        <div style='font-family:sans-serif;max-width:600px;margin:0 auto;padding:20px'>
            <div style='background:#237f4e;padding:20px;border-radius:8px 8px 0 0;text-align:center'>
                <h1 style='color:white;margin:0;font-size:22px'>💹 FINOVATE</h1>
                <p style='color:rgba(255,255,255,0.8);margin:6px 0 0;font-size:13px'>Plateforme de Forums Financiers</p>
            </div>
            <div style='background:white;padding:28px;border:1px solid #e0e0e0;border-top:none;border-radius:0 0 8px 8px'>
                <p style='font-size:16px;color:#333'>Bonjour <strong>{$user->getFirstname()}</strong>,</p>
                <p style='color:#555;line-height:1.6'>Votre contenu <strong>\"$contentTitle\"</strong> a été supprimé de la plateforme FINOVATE.</p>
                <div style='background:#fff3f3;border-left:4px solid #e53e3e;padding:12px 16px;margin:16px 0;border-radius:4px'>
                    <strong style='color:#e53e3e'>Raison :</strong> <span style='color:#555'>$reason</span>
                </div>
                <p style='color:#555;line-height:1.6'>$specificMessage</p>
                $offenseWarning
                <p style='color:#555;line-height:1.6'>Consultez notre <a href='http://localhost:8000/forum/policy' style='color:#237f4e;font-weight:600'>politique de la communauté</a> pour éviter de futures infractions.</p>
                <hr style='border:none;border-top:1px solid #e0e0e0;margin:20px 0'>
                <p style='color:#888;font-size:13px'>Cordialement,<br><strong>L'équipe FINOVATE</strong></p>
            </div>
        </div>";
    }

    private function buildWarningBody(User $user, string $reason): string
    {
        $specificMessage = match(true) {
            str_contains($reason, 'Misinformation') || str_contains($reason, 'Fausses') =>
                "Nous avons détecté que vous avez partagé des <strong>informations financières non vérifiées</strong>. Avant de publier, assurez-vous que vos informations sont sourcées et vérifiables.",
            str_contains($reason, 'toxique') || str_contains($reason, 'offensant') =>
                "Votre comportement a été jugé <strong>offensant ou toxique</strong> envers d'autres membres. FINOVATE exige le respect mutuel entre tous les participants.",
            str_contains($reason, 'Spam') =>
                "Vous avez publié du <strong>contenu répétitif ou du spam</strong>. Chaque publication doit apporter une valeur réelle à la communauté.",
            str_contains($reason, 'financier') || str_contains($reason, 'trompeur') =>
                "Vous avez partagé des <strong>informations financières trompeuses</strong>. Cela peut nuire à nos membres qui font confiance à la plateforme.",
            default =>
                "Votre comportement récent n'était pas conforme à nos règles communautaires.",
        };

        return "
        <div style='font-family:sans-serif;max-width:600px;margin:0 auto;padding:20px'>
            <div style='background:#f59e0b;padding:20px;border-radius:8px 8px 0 0;text-align:center'>
                <h1 style='color:white;margin:0;font-size:22px'>💹 FINOVATE — ⚠️ Avertissement</h1>
            </div>
            <div style='background:white;padding:28px;border:1px solid #e0e0e0;border-top:none;border-radius:0 0 8px 8px'>
                <p style='font-size:16px;color:#333'>Bonjour <strong>{$user->getFirstname()}</strong>,</p>
                <p style='color:#555;line-height:1.6'>Ceci est votre <strong style='color:#d97706'>premier et dernier avertissement</strong>.</p>
                <div style='background:#fffbeb;border-left:4px solid #f59e0b;padding:12px 16px;margin:16px 0;border-radius:4px'>
                    <strong style='color:#d97706'>Raison :</strong> <span style='color:#555'>$reason</span>
                </div>
                <p style='color:#555;line-height:1.6'>$specificMessage</p>
                <div style='background:#fff3cd;border-left:4px solid #f59e0b;padding:12px 16px;margin:16px 0;border-radius:4px'>
                    <strong style='color:#92400e'>⚠️ Conséquences en cas de récidive :</strong>
                    <ul style='color:#555;margin:8px 0 0 16px;line-height:1.8'>
                        <li>2ème infraction : Restriction temporaire du compte</li>
                        <li>3ème infraction : Bannissement définitif</li>
                    </ul>
                </div>
                <p style='color:#555;line-height:1.6'>Consultez notre <a href='http://localhost:8000/forum/policy' style='color:#237f4e;font-weight:600'>politique de la communauté</a>.</p>
                <hr style='border:none;border-top:1px solid #e0e0e0;margin:20px 0'>
                <p style='color:#888;font-size:13px'>Cordialement,<br><strong>L'équipe FINOVATE</strong></p>
            </div>
        </div>";
    }

    private function buildRestrictionBody(User $user, RestrictionDto $dto): string
    {
        $duration = $dto->days > 0 ? "{$dto->days} jours" : "indéfiniment";
        $canPost  = $dto->canPost    ? '✅ Autorisé' : '❌ Interdit';
        $canCom   = $dto->canComment ? '✅ Autorisé' : '❌ Interdit';
        $canForum = $dto->canCreateForum ? '✅ Autorisé' : '❌ Interdit';
        return "
        <div style='font-family:sans-serif;max-width:600px;margin:0 auto;padding:20px'>
            <div style='background:#237f4e;padding:20px;border-radius:8px 8px 0 0;text-align:center'>
                <h1 style='color:white;margin:0;font-size:22px'>💹 FINOVATE</h1>
            </div>
            <div style='background:white;padding:28px;border:1px solid #e0e0e0;border-top:none;border-radius:0 0 8px 8px'>
                <p style='font-size:16px;color:#333'>Bonjour <strong>{$user->getFirstname()}</strong>,</p>
                <p style='color:#555;line-height:1.6'>Votre compte a été restreint pour <strong>$duration</strong>.</p>
                <div style='background:#fff3f3;border-left:4px solid #e53e3e;padding:12px 16px;margin:16px 0;border-radius:4px'>
                    <strong style='color:#e53e3e'>Raison :</strong> <span style='color:#555'>{$dto->reason}</span>
                </div>
                <table style='width:100%;border-collapse:collapse;margin:16px 0'>
                    <tr style='background:#f9f9f9'><td style='padding:8px 12px;border:1px solid #e0e0e0'>Publier des posts</td><td style='padding:8px 12px;border:1px solid #e0e0e0'>$canPost</td></tr>
                    <tr><td style='padding:8px 12px;border:1px solid #e0e0e0'>Commenter</td><td style='padding:8px 12px;border:1px solid #e0e0e0'>$canCom</td></tr>
                    <tr style='background:#f9f9f9'><td style='padding:8px 12px;border:1px solid #e0e0e0'>Créer des forums</td><td style='padding:8px 12px;border:1px solid #e0e0e0'>$canForum</td></tr>
                </table>
                <p style='color:#888;font-size:13px;margin-top:24px'>L'équipe FINOVATE</p>
            </div>
        </div>";
    }

    private function buildBanBody(User $user, string $reason): string
    {
        return "
        <div style='font-family:sans-serif;max-width:600px;margin:0 auto;padding:20px'>
            <div style='background:#1a1a1b;padding:20px;border-radius:8px 8px 0 0;text-align:center'>
                <h1 style='color:white;margin:0;font-size:22px'>💹 FINOVATE</h1>
            </div>
            <div style='background:white;padding:28px;border:1px solid #e0e0e0;border-top:none;border-radius:0 0 8px 8px'>
                <p style='font-size:16px;color:#333'>Bonjour <strong>{$user->getFirstname()}</strong>,</p>
                <p style='color:#555;line-height:1.6'>Suite à des infractions répétées, votre compte a été <strong style='color:#e53e3e'>définitivement banni</strong>.</p>
                <div style='background:#fff3f3;border-left:4px solid #e53e3e;padding:12px 16px;margin:16px 0;border-radius:4px'>
                    <strong style='color:#e53e3e'>Raison :</strong> <span style='color:#555'>$reason</span>
                </div>
                <p style='color:#888;font-size:13px;margin-top:24px'>L'équipe FINOVATE</p>
            </div>
        </div>";
    }
}
