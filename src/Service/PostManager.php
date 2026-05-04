<?php

namespace App\Service;

use App\Entity\Post;
use App\Entity\User;

/**
 * Service métier pour la validation des règles métier de l'entité Post.
 */
class PostManager
{
    /**
     * Valide les règles métier d'un post.
     *
     * Règles validées:
     * 1. Le titre doit contenir entre 3 et 200 caractères
     * 2. Le contenu doit contenir entre 10 et 10000 caractères
     * 3. L'auteur doit être défini
     * 4. Le forum doit être défini
     *
     * @param Post $post Le post à valider
     * @return bool True si le post est valide
     * @throws \InvalidArgumentException Si une règle métier n'est pas respectée
     */
    public function validate(Post $post): bool
    {
        $title = trim($post->getTitle());
        $titleLength = mb_strlen($title);
        
        if ($titleLength < 3 || $titleLength > 200) {
            throw new \InvalidArgumentException('Le titre doit contenir entre 3 et 200 caractères.');
        }

        $content = trim($post->getContent());
        $contentLength = mb_strlen($content);
        
        if ($contentLength < 10) {
            throw new \InvalidArgumentException('Le contenu doit contenir au moins 10 caractères.');
        }
        
        if ($contentLength > 10000) {
            throw new \InvalidArgumentException('Le contenu ne peut pas dépasser 10000 caractères.');
        }

        if ($post->getAuthor() === null) {
            throw new \InvalidArgumentException('L\'auteur du post est obligatoire.');
        }

        if ($post->getForum() === null) {
            throw new \InvalidArgumentException('Le forum du post est obligatoire.');
        }

        return true;
    }

    public function isTitleValid(Post $post): bool
    {
        $title = trim($post->getTitle());
        $length = mb_strlen($title);
        return $length >= 3 && $length <= 200;
    }

    public function isContentValid(Post $post): bool
    {
        $content = trim($post->getContent());
        $length = mb_strlen($content);
        return $length >= 10 && $length <= 10000;
    }

    public function hasAuthor(Post $post): bool
    {
        return $post->getAuthor() !== null;
    }

    public function hasForum(Post $post): bool
    {
        return $post->getForum() !== null;
    }

    public function isAuthor(Post $post, User $user): bool
    {
        $author = $post->getAuthor();
        return $author !== null && $author->getId() === $user->getId();
    }

    public function canEdit(Post $post, User $user): bool
    {
        return $this->isAuthor($post, $user);
    }

    public function canDelete(Post $post, User $user): bool
    {
        return $this->isAuthor($post, $user);
    }

    public function getCommentCount(Post $post): int
    {
        return $post->getComments()->count();
    }

    public function getUpvoteCount(Post $post): int
    {
        return $post->getUpvoteCount();
    }

    public function getDownvoteCount(Post $post): int
    {
        return $post->getDownvoteCount();
    }

    public function getNetVotes(Post $post): int
    {
        return $this->getUpvoteCount($post) - $this->getDownvoteCount($post);
    }
}
