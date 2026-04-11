<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserBlock;
use App\Entity\UserPeerRestriction;
use App\Repository\UserBlockRepository;
use App\Repository\UserPeerRestrictionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class UserRelationService
{
    private const BREVO_URL = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(
        private EntityManagerInterface $em,
        private UserBlockRepository $blockRepo,
        private UserPeerRestrictionRepository $restrictRepo,
        private HttpClientInterface $http,
        private LoggerInterface $logger,
        private string $brevoApiKey,
        private string $fromEmail = 'noreply@finovate.com',
        private string $fromName  = 'FINOVATE',
    ) {}

    // ── Block ─────────────────────────────────────────────────────────────────

    public function block(User $blocker, User $blocked, string $reason): void
    {
        $existing = $this->blockRepo->findBlock($blocker->getId(), $blocked->getId());
        if ($existing) return;

        $block = new UserBlock();
        $block->setBlocker($blocker);
        $block->setBlocked($blocked);
        $block->setReason($reason ?: null);
        $this->em->persist($block);
        $this->em->flush();

        $this->sendEmail(
            $blocked->getEmail(),
            $blocked->getFirstname(),
            '[FINOVATE] Vous avez été bloqué par un utilisateur',
            $this->buildBlockEmail($blocked, $blocker, $reason)
        );
    }

    public function unblock(User $blocker, User $blocked): void
    {
        $block = $this->blockRepo->findBlock($blocker->getId(), $blocked->getId());
        if ($block) {
            $this->em->remove($block);
            $this->em->flush();
        }
    }

    public function isBlocked(int $blockerId, int $blockedId): bool
    {
        return $this->blockRepo->findBlock($blockerId, $blockedId) !== null;
    }

    public function isMutuallyBlocked(int $userA, int $userB): bool
    {
        return $this->isBlocked($userA, $userB) || $this->isBlocked($userB, $userA);
    }

    // ── Peer Restrict ─────────────────────────────────────────────────────────

    public function restrict(User $restrictor, User $restricted, string $reason, ?int $days): void
    {
        // Deactivate existing
        $existing = $this->restrictRepo->findActive($restrictor->getId(), $restricted->getId());
        if ($existing) {
            $existing->setActive(false);
            $this->em->flush();
        }

        $r = new UserPeerRestriction();
        $r->setRestrictor($restrictor);
        $r->setRestricted($restricted);
        $r->setReason($reason ?: null);
        if ($days !== null && $days > 0) {
            $r->setRestrictedUntil(new \DateTime("+{$days} days"));
        }
        $this->em->persist($r);
        $this->em->flush();

        $duration = ($days && $days > 0) ? "{$days} jours" : "jusqu'à levée manuelle";
        $this->sendEmail(
            $restricted->getEmail(),
            $restricted->getFirstname(),
            '[FINOVATE] Vous avez été restreint par un utilisateur',
            $this->buildRestrictEmail($restricted, $restrictor, $reason, $duration)
        );
    }

    public function unrestrict(User $restrictor, User $restricted): void
    {
        $r = $this->restrictRepo->findActive($restrictor->getId(), $restricted->getId());
        if ($r) {
            $r->setActive(false);
            $this->em->flush();
        }
    }

    public function isPeerRestricted(int $restrictorId, int $restrictedId): bool
    {
        $r = $this->restrictRepo->findActive($restrictorId, $restrictedId);
        if (!$r) return false;
        if ($r->isExpired()) {
            $r->setActive(false);
            $this->em->flush();
            return false;
        }
        return true;
    }

    // ── Email ─────────────────────────────────────────────────────────────────

    private function sendEmail(string $to, string $name, string $subject, string $html): void
    {
        try {
            $this->http->request('POST', self::BREVO_URL, [
                'headers' => [
                    'api-key'      => $this->brevoApiKey,
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'json' => [
                    'sender'      => ['name' => $this->fromName, 'email' => $this->fromEmail],
                    'to'          => [['email' => $to, 'name' => $name]],
                    'subject'     => $subject,
                    'htmlContent' => $html,
                ],
            ])->getStatusCode();
        } catch (\Throwable $e) {
            $this->logger->warning('UserRelationService email failed: ' . $e->getMessage());
        }
    }

    private function buildBlockEmail(User $blocked, User $blocker, string $reason): string
    {
        $reasonHtml = $reason ? "<div style='background:#fff3f3;border-left:4px solid #e53e3e;padding:12px 16px;margin:16px 0;border-radius:4px'><strong style='color:#e53e3e'>Raison :</strong> <span style='color:#555'>" . htmlspecialchars($reason) . "</span></div>" : '';
        return "
        <div style='font-family:sans-serif;max-width:600px;margin:0 auto;padding:20px'>
            <div style='background:#1a1a1b;padding:20px;border-radius:8px 8px 0 0;text-align:center'>
                <h1 style='color:white;margin:0;font-size:22px'>💹 FINOVATE</h1>
            </div>
            <div style='background:white;padding:28px;border:1px solid #e0e0e0;border-top:none;border-radius:0 0 8px 8px'>
                <p style='font-size:16px;color:#333'>Bonjour <strong>{$blocked->getFirstname()}</strong>,</p>
                <p style='color:#555;line-height:1.6'>Un utilisateur vous a <strong>bloqué</strong> sur FINOVATE.</p>
                $reasonHtml
                <p style='color:#555;line-height:1.6'>Cela signifie que :</p>
                <ul style='color:#555;line-height:2;margin-left:20px'>
                    <li>Vous ne verrez plus ses posts, commentaires ni forums</li>
                    <li>Il ne verra plus vos posts, commentaires ni forums</li>
                </ul>
                <hr style='border:none;border-top:1px solid #e0e0e0;margin:20px 0'>
                <p style='color:#888;font-size:13px'>L'équipe FINOVATE</p>
            </div>
        </div>";
    }

    private function buildRestrictEmail(User $restricted, User $restrictor, string $reason, string $duration): string
    {
        $reasonHtml = $reason ? "<div style='background:#fffbeb;border-left:4px solid #f59e0b;padding:12px 16px;margin:16px 0;border-radius:4px'><strong style='color:#d97706'>Raison :</strong> <span style='color:#555'>" . htmlspecialchars($reason) . "</span></div>" : '';
        return "
        <div style='font-family:sans-serif;max-width:600px;margin:0 auto;padding:20px'>
            <div style='background:#f59e0b;padding:20px;border-radius:8px 8px 0 0;text-align:center'>
                <h1 style='color:white;margin:0;font-size:22px'>💹 FINOVATE — ⏱ Restriction</h1>
            </div>
            <div style='background:white;padding:28px;border:1px solid #e0e0e0;border-top:none;border-radius:0 0 8px 8px'>
                <p style='font-size:16px;color:#333'>Bonjour <strong>{$restricted->getFirstname()}</strong>,</p>
                <p style='color:#555;line-height:1.6'>Un utilisateur vous a <strong>restreint</strong> pour <strong>$duration</strong>.</p>
                $reasonHtml
                <p style='color:#555;line-height:1.6'>Pendant cette période :</p>
                <ul style='color:#555;line-height:2;margin-left:20px'>
                    <li>Vous pouvez voir ses posts et forums</li>
                    <li>Vous <strong>ne pouvez pas</strong> commenter, voter, partager ni rejoindre ses forums</li>
                </ul>
                <hr style='border:none;border-top:1px solid #e0e0e0;margin:20px 0'>
                <p style='color:#888;font-size:13px'>L'équipe FINOVATE</p>
            </div>
        </div>";
    }
}
