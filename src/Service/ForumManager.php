<?php

namespace App\Service;

use App\Entity\Forum;
use App\Entity\User;

/**
 * Service métier pour la validation des règles métier de l'entité Forum.
 */
class ForumManager
{
    /**
     * Valide les règles métier d'un forum.
     *
     * Règles validées:
     * 1. Le titre doit contenir entre 3 et 200 caractères
     * 2. Le créateur doit être défini
     *
     * @param Forum $forum Le forum à valider
     * @return bool True si le forum est valide
     * @throws \InvalidArgumentException Si une règle métier n'est pas respectée
     */
    public function validate(Forum $forum): bool
    {
        $title = trim($forum->getTitle());
        $titleLength = mb_strlen($title);
        
        if ($titleLength < 3 || $titleLength > 200) {
            throw new \InvalidArgumentException('Le titre doit contenir entre 3 et 200 caractères.');
        }

        if ($forum->getCreator() === null) {
            throw new \InvalidArgumentException('Le créateur du forum est obligatoire.');
        }

        return true;
    }

    public function isTitleValid(Forum $forum): bool
    {
        $title = trim($forum->getTitle());
        $length = mb_strlen($title);
        return $length >= 3 && $length <= 200;
    }

    public function hasCreator(Forum $forum): bool
    {
        return $forum->getCreator() !== null;
    }

    public function isCreator(Forum $forum, User $user): bool
    {
        $creator = $forum->getCreator();
        return $creator !== null && $creator->getId() === $user->getId();
    }

    public function getPostCount(Forum $forum): int
    {
        return $forum->getPosts()->count();
    }

    public function getMemberCount(Forum $forum): int
    {
        return $forum->getMembers()->count();
    }
}
