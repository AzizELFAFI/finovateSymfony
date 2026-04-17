<?php

namespace App\Service;

use App\Entity\Alert;
use App\Entity\UserBadge;
use App\Repository\BadgeTypeRepository;
use App\Repository\UserBadgeRepository;
use App\Repository\PostRepository;
use App\Repository\CommentRepository;
use App\Repository\VoteRepository;
use App\Repository\SharedPostRepository;
use App\Repository\UserForumRepository;
use Doctrine\ORM\EntityManagerInterface;

class AlertService
{
    public function __construct(
        private EntityManagerInterface $em,
        private BadgeTypeRepository    $badgeTypeRepo,
        private UserBadgeRepository    $userBadgeRepo,
        private PostRepository         $postRepo,
        private CommentRepository      $commentRepo,
        private VoteRepository         $voteRepo,
        private SharedPostRepository   $sharedRepo,
        private UserForumRepository    $userForumRepo,
        private NotificationService    $notif,
    ) {}

    // ── Create alert ─────────────────────────────────────────────────────────

    public function create(int $userId, string $type, string $message, ?string $url = null): void
    {
        $user = $this->em->getReference(\App\Entity\User::class, $userId);
        $alert = new Alert();
        $alert->setUser($user);
        $alert->setType($type);
        $alert->setMessage($message);
        $alert->setRelatedUrl($url);
        $this->em->persist($alert);

        // Fire push notification to all subscribed browsers for this user
        $icon = match($type) {
            Alert::TYPE_BADGE       => '🏆',
            Alert::TYPE_MODERATION  => '🚨',
            Alert::TYPE_WARNING     => '⚠️',
            Alert::TYPE_RESTRICTION => '⏱',
            Alert::TYPE_BAN         => '🚫',
            Alert::TYPE_NEW_POST    => '📝',
            Alert::TYPE_COMMENT     => '💬',
            Alert::TYPE_VOTE        => '👍',
            Alert::TYPE_SHARE       => '📤',
            Alert::TYPE_JOIN        => '👥',
            Alert::TYPE_LEAVE       => '👋',
            default                 => '🔔',
        };
        $this->notif->sendToAll($icon . ' FINOVATE', $message, $url ?? '/');
        // don't flush here — caller flushes
    }

    // ── Badge check & award ───────────────────────────────────────────────────

    /**
     * Check all badge conditions for a user and award any new ones.
     * Returns array of newly awarded BadgeType names (for popup).
     */
    public function checkAndAwardBadges(int $userId): array
    {
        $user      = $this->em->getReference(\App\Entity\User::class, $userId);
        $allBadges = $this->badgeTypeRepo->findAll();

        // Weekly reset: only consider badges earned THIS week
        $weekStart = new \DateTimeImmutable('monday this week midnight');
        $earnedIds = array_map(
            fn($ub) => $ub->getBadgeType()->getId(),
            $this->userBadgeRepo->createQueryBuilder('ub')
                ->where('ub.user = :uid AND ub.earnedAt >= :ws')
                ->setParameter('uid', $userId)
                ->setParameter('ws', $weekStart)
                ->getQuery()->getResult()
        );

        $postCount    = count($this->postRepo->createQueryBuilder('p')->where('p.author = :uid')->setParameter('uid', $userId)->getQuery()->getResult());
        $commentCount = count($this->commentRepo->createQueryBuilder('c')->where('c.author = :uid')->setParameter('uid', $userId)->getQuery()->getResult());
        $voteCount    = count($this->voteRepo->createQueryBuilder('v')->where('v.user = :uid')->setParameter('uid', $userId)->getQuery()->getResult());
        $shareCount   = count($this->sharedRepo->createQueryBuilder('s')->where('s.user = :uid')->setParameter('uid', $userId)->getQuery()->getResult());
        $forumCount   = count($this->userForumRepo->createQueryBuilder('uf')->where('uf.user = :uid')->setParameter('uid', $userId)->getQuery()->getResult());

        // Today's votes for Super Fan
        $today = new \DateTime('today');
        $todayVotes = count($this->voteRepo->createQueryBuilder('v')
            ->where('v.user = :uid AND v.createdAt >= :today')
            ->setParameter('uid', $userId)->setParameter('today', $today)
            ->getQuery()->getResult());

        // Night owl: posted between midnight and 5am (PHP-side filter)
        $allPosts  = $this->postRepo->createQueryBuilder('p')
            ->where('p.author = :uid')->setParameter('uid', $userId)
            ->getQuery()->getResult();
        $nightPost = null;
        foreach ($allPosts as $p) {
            $hour = (int) $p->getCreatedAt()->format('H');
            if ($hour >= 0 && $hour < 5) { $nightPost = $p; break; }
        }

        $newBadges = [];

        foreach ($allBadges as $badge) {
            if (in_array($badge->getId(), $earnedIds)) continue;

            $reqType  = strtolower($badge->getRequirementType());
            $reqValue = $badge->getRequirementValue();

            // Map requirementType to actual count
            $earned = match($reqType) {
                'posts'    => $postCount    >= $reqValue,
                'comments' => $commentCount >= $reqValue,
                'votes'    => $voteCount    >= $reqValue,
                'shares'   => $shareCount   >= $reqValue,
                'forums'   => $forumCount   >= $reqValue,
                'today_votes' => $todayVotes >= $reqValue,
                'night_post'  => $nightPost !== null,
                // Legacy name-based fallback for old badges
                default => match(true) {
                    str_contains($badge->getName(), 'Premier Post')     => $postCount >= 1,
                    str_contains($badge->getName(), 'Bronze')           => $postCount >= 3,
                    str_contains($badge->getName(), 'Silver')           => $postCount >= 5,
                    str_contains($badge->getName(), 'Gold')             => $postCount >= 10,
                    str_contains($badge->getName(), 'Diamond')          => $postCount >= 15,
                    str_contains($badge->getName(), 'Legendaire')       => $postCount >= 20,
                    str_contains($badge->getName(), 'Bavard')           => $commentCount >= 5,
                    str_contains($badge->getName(), 'Commentateur Pro') => $commentCount >= 20,
                    str_contains($badge->getName(), 'Super Fan')        => $todayVotes >= 5,
                    str_contains($badge->getName(), 'Influenceur')      => $voteCount >= 10,
                    str_contains($badge->getName(), 'Populaire')        => $voteCount >= 5,
                    str_contains($badge->getName(), 'Explorateur')      => $forumCount >= 3,
                    str_contains($badge->getName(), 'Partageur')        => $shareCount >= 3,
                    str_contains($badge->getName(), 'Noctambule')       => $nightPost !== null,
                    default => false,
                },
            };

            if ($earned) {
                $ub = new UserBadge();
                $ub->setUser($user);
                $ub->setBadgeType($badge);
                $this->em->persist($ub);

                $this->create(
                    $userId,
                    Alert::TYPE_BADGE,
                    ($badge->getIcon() ?? '🏆') . ' Nouveau badge débloqué : ' . $badge->getName() . ' ! ' . $badge->getDescription()
                );

                $newBadges[] = [
                    'name' => $badge->getName(),
                    'icon' => $badge->getIcon() ?? '🏆',
                    'desc' => $badge->getDescription(),
                ];
            }
        }

        return $newBadges;
    }
}
