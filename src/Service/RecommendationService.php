<?php

namespace App\Service;

use App\Entity\ForumRecommendation;
use App\Repository\ForumRepository;
use App\Repository\ForumRecommendationRepository;
use App\Repository\UserForumRepository;
use App\Repository\UserInteractionRepository;
use Doctrine\ORM\EntityManagerInterface;

class RecommendationService
{
    // Weight per interaction type
    private const WEIGHTS = [
        'VOTE'    => 3,
        'COMMENT' => 2,
        'POST'    => 2,
        'SHARE'   => 2,
        'JOIN'    => 1,
        'VIEW'    => 1,
        'CLICK'   => 1,
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private ForumRepository $forumRepo,
        private ForumRecommendationRepository $recRepo,
        private UserForumRepository $ufRepo,
        private UserInteractionRepository $interactionRepo,
    ) {}

    /**
     * Recompute and persist recommendations for a given user.
     */
    public function refresh(int $userId): void
    {
        // Forums the user already joined
        $joinedIds = array_map(
            fn($uf) => $uf->getForum()->getId(),
            $this->ufRepo->createQueryBuilder('uf')
                ->where('uf.user = :uid')->setParameter('uid', $userId)
                ->getQuery()->getResult()
        );

        // All interactions of this user
        $interactions = $this->interactionRepo->createQueryBuilder('i')
            ->where('i.user = :uid')->setParameter('uid', $userId)
            ->getQuery()->getResult();

        // Build score map: forumId => score
        $scores = [];
        foreach ($interactions as $interaction) {
            $forumId = $interaction->getForum()?->getId();
            if (!$forumId || in_array($forumId, $joinedIds)) continue;

            $weight = self::WEIGHTS[$interaction->getInteractionType()] ?? 1;
            $scores[$forumId] = ($scores[$forumId] ?? 0)
                + ($weight * ($interaction->getInteractionCount() ?? 1));
        }

        // Bonus: popular forums (many members) not yet joined
        $allForums = $this->forumRepo->findAll();
        foreach ($allForums as $forum) {
            if (in_array($forum->getId(), $joinedIds)) continue;
            $memberCount = $forum->getMembers()->count();
            if ($memberCount >= 5) {
                $scores[$forum->getId()] = ($scores[$forum->getId()] ?? 0) + min($memberCount, 10);
            }
        }

        // Remove forums with zero score
        $scores = array_filter($scores, fn($s) => $s > 0);

        // Upsert ForumRecommendation rows
        $userRef = $this->em->getReference(\App\Entity\User::class, $userId);

        foreach ($scores as $forumId => $score) {
            $existing = $this->recRepo->createQueryBuilder('r')
                ->where('r.user = :uid AND r.forum = :fid')
                ->setParameter('uid', $userId)
                ->setParameter('fid', $forumId)
                ->getQuery()->getOneOrNullResult();

            $forum = $this->forumRepo->find($forumId);
            if (!$forum) continue;

            $reason = $this->buildReason($interactions, $forumId, $forum->getMembers()->count());

            if ($existing) {
                $existing->setScore($score);
                $existing->setReason($reason);
            } else {
                $rec = new ForumRecommendation();
                $rec->setUser($userRef);
                $rec->setForum($forum);
                $rec->setScore($score);
                $rec->setReason($reason);
                $this->em->persist($rec);
            }
        }

        // Remove stale recommendations for forums the user has now joined
        $stale = $this->recRepo->createQueryBuilder('r')
            ->where('r.user = :uid')->setParameter('uid', $userId)
            ->getQuery()->getResult();

        foreach ($stale as $rec) {
            if (in_array($rec->getForum()?->getId(), $joinedIds)) {
                $this->em->remove($rec);
            }
        }

        $this->em->flush();
    }

    private function buildReason(array $interactions, int $forumId, int $memberCount): string
    {
        $types = [];
        foreach ($interactions as $i) {
            if ($i->getForum()?->getId() === $forumId) {
                $types[] = $i->getInteractionType();
            }
        }

        $parts = [];
        if (in_array('VOTE', $types))    $parts[] = 'vous avez voté sur des posts similaires';
        if (in_array('COMMENT', $types)) $parts[] = 'vous avez commenté dans ce forum';
        if (in_array('VIEW', $types))    $parts[] = 'vous avez consulté ce forum';
        if ($memberCount >= 5)           $parts[] = $memberCount . ' membres actifs';

        return $parts ? ucfirst(implode(', ', $parts)) . '.' : 'Forum populaire dans votre réseau.';
    }
}
