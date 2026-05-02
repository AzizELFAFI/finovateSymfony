<?php

namespace App\Service;

use App\Entity\Comment;
use App\Entity\User;

/**
 * Service métier pour la validation des règles métier de l'entité Comment.
 */
class CommentManager
{
    /**
     * Valide les règles métier d'un commentaire.
     *
     * Règles validées:
     * 1. Le contenu doit contenir au moins 1 caractère
     * 2. Le contenu ne doit pas dépasser 2000 caractères
     * 3. L'auteur doit être défini
     * 4. Le post doit être défini
     *
     * @param Comment $comment Le commentaire à valider
     * @return bool True si le commentaire est valide
     * @throws \InvalidArgumentException Si une règle métier n'est pas respectée
     */
    public function validate(Comment $comment): bool
    {
        $content = trim($comment->getContent());
        $contentLength = mb_strlen($content);
        
        if ($contentLength < 1) {
            throw new \InvalidArgumentException('Le contenu ne peut pas être vide.');
        }
        
        if ($contentLength > 2000) {
            throw new \InvalidArgumentException('Le contenu ne peut pas dépasser 2000 caractères.');
        }

        if ($comment->getAuthor() === null) {
            throw new \InvalidArgumentException('L\'auteur du commentaire est obligatoire.');
        }

        if ($comment->getPost() === null) {
            throw new \InvalidArgumentException('Le post du commentaire est obligatoire.');
        }

        return true;
    }

    public function isContentValid(Comment $comment): bool
    {
        $content = trim($comment->getContent());
        $length = mb_strlen($content);
        return $length >= 1 && $length <= 2000;
    }

    public function hasAuthor(Comment $comment): bool
    {
        return $comment->getAuthor() !== null;
    }

    public function hasPost(Comment $comment): bool
    {
        return $comment->getPost() !== null;
    }

    public function isAuthor(Comment $comment, User $user): bool
    {
        $author = $comment->getAuthor();
        return $author !== null && $author->getId() === $user->getId();
    }

    public function canEdit(Comment $comment, User $user): bool
    {
        return $this->isAuthor($comment, $user);
    }

    public function canDelete(Comment $comment, User $user): bool
    {
        if ($this->isAuthor($comment, $user)) {
            return true;
        }
        
        $post = $comment->getPost();
        if ($post !== null && $post->getAuthor() !== null) {
            return $post->getAuthor()->getId() === $user->getId();
        }
        
        return false;
    }

    public function isRecent(Comment $comment, int $minutes = 5): bool
    {
        $now = new \DateTime();
        $interval = $now->diff($comment->getCreatedAt());
        $totalMinutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
        
        return $totalMinutes <= $minutes;
    }
}
