<?php
namespace App\Service;

use App\Dto\RestrictionDto;
use App\Entity\User;
use App\Entity\UserRestriction;
use App\Repository\UserRestrictionRepository;
use Doctrine\ORM\EntityManagerInterface;

class AdminRestrictionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRestrictionRepository $restrictionRepo,
    ) {}

    public function applyRestriction(User $user, RestrictionDto $dto): UserRestriction
    {
        // Deactivate existing active restriction
        $existing = $this->restrictionRepo->findActiveForUser($user->getId());
        if ($existing) {
            $existing->setActive(false);
            $this->em->flush();
        }

        $restriction = new UserRestriction();
        $restriction->setUser($user);
        $restriction->setCanPost($dto->canPost);
        $restriction->setCanComment($dto->canComment);
        $restriction->setCanCreateForum($dto->canCreateForum);
        $restriction->setReason($dto->reason);
        $restriction->setOffenseNumber($dto->offenseNumber);

        if ($dto->days > 0) {
            $restriction->setRestrictedUntil(new \DateTimeImmutable("+{$dto->days} days"));
        }

        $this->em->persist($restriction);
        $this->em->flush();

        return $restriction;
    }

    public function banUser(User $user): void
    {
        $user->setRole('BANNED');
        $this->em->flush();
    }

    public function getActiveRestriction(User $user): ?UserRestriction
    {
        $r = $this->restrictionRepo->findActiveForUser($user->getId());
        if ($r && $r->getRestrictedUntil() && $r->getRestrictedUntil() < new \DateTimeImmutable()) {
            $r->setActive(false);
            $this->em->flush();
            return null;
        }
        return $r;
    }

    public function isRestricted(User $user, string $action): bool
    {
        if ($user->getRole() === 'BANNED') return true;
        $r = $this->getActiveRestriction($user);
        if (!$r) return false;
        return match($action) {
            'post'         => !$r->isCanPost(),
            'comment'      => !$r->isCanComment(),
            'create_forum' => !$r->isCanCreateForum(),
            default        => false,
        };
    }
}
