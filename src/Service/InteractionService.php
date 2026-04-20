<?php

namespace App\Service;

use App\Entity\UserInteraction;
use App\Repository\UserInteractionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Records and increments user interactions with forums.
 * Does NOT flush — caller is responsible for flushing.
 */
class InteractionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserInteractionRepository $repo,
        private RecommendationService $recommendationService,
    ) {}

    /**
     * Track an interaction and immediately refresh recommendations.
     *
     * @param int    $userId
     * @param int    $forumId
     * @param string $type  VIEW | CLICK | JOIN | POST | COMMENT | VOTE | SHARE
     */
    public function track(int $userId, int $forumId, string $type): void
    {
        $existing = $this->repo->createQueryBuilder('i')
            ->where('i.user = :uid AND i.forum = :fid AND i.interactionType = :type')
            ->setParameter('uid', $userId)
            ->setParameter('fid', $forumId)
            ->setParameter('type', $type)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existing) {
            $existing->setInteractionCount(($existing->getInteractionCount() ?? 0) + 1);
            $existing->setLastInteraction(new \DateTime());
        } else {
            $interaction = new UserInteraction();
            $interaction->setUser($this->em->getReference(\App\Entity\User::class, $userId));
            $interaction->setForum($this->em->getReference(\App\Entity\Forum::class, $forumId));
            $interaction->setInteractionType($type);
            $interaction->setInteractionCount(1);
            $this->em->persist($interaction);
        }

        // Flush the interaction first, then recompute recommendations
        $this->em->flush();
        $this->recommendationService->refresh($userId);
    }
}
