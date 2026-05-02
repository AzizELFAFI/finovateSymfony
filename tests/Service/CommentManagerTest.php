<?php

namespace App\Tests\Service;

use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\User;
use App\Service\CommentManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le service CommentManager.
 * 
 * Règles métier testées:
 * 1. Le contenu doit contenir au moins 1 caractère
 * 2. Le contenu ne doit pas dépasser 2000 caractères
 * 3. L'auteur doit être défini
 * 4. Le post doit être défini
 */
class CommentManagerTest extends TestCase
{
    private CommentManager $commentManager;

    protected function setUp(): void
    {
        $this->commentManager = new CommentManager();
    }

    private function createComment(string $content, ?User $author = null, ?Post $post = null): Comment
    {
        $comment = new Comment();
        $comment->setContent($content);
        
        if ($author !== null) {
            $comment->setAuthor($author);
        }
        
        if ($post !== null) {
            $comment->setPost($post);
        }
        
        return $comment;
    }

    private function createUser(int $id = 1): User
    {
        $user = new User();
        $reflection = new \ReflectionClass($user);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($user, $id);
        
        return $user;
    }

    private function createPost(?User $author = null): Post
    {
        $post = new Post();
        $post->setTitle('Test Post');
        $post->setContent('Test content');
        
        if ($author !== null) {
            $post->setAuthor($author);
        }
        
        return $post;
    }

    public function testValidComment(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();
        $comment = $this->createComment('Ceci est un commentaire valide.', $user, $post);
        
        $this->assertTrue($this->commentManager->validate($comment));
    }

    public function testCommentWithEmptyContent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le contenu ne peut pas être vide.');
        
        $user = $this->createUser();
        $post = $this->createPost();
        $comment = $this->createComment('', $user, $post);
        
        $this->commentManager->validate($comment);
    }

    public function testCommentWithWhitespaceContent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le contenu ne peut pas être vide.');
        
        $user = $this->createUser();
        $post = $this->createPost();
        $comment = $this->createComment('   ', $user, $post);
        
        $this->commentManager->validate($comment);
    }

    public function testCommentWithLongContent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le contenu ne peut pas dépasser 2000 caractères.');
        
        $user = $this->createUser();
        $post = $this->createPost();
        $longContent = str_repeat('A', 2001);
        $comment = $this->createComment($longContent, $user, $post);
        
        $this->commentManager->validate($comment);
    }

    public function testCommentWithExactlyOneCharacter(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();
        $comment = $this->createComment('A', $user, $post);
        
        $this->assertTrue($this->commentManager->validate($comment));
    }

    public function testCommentWithExactly2000Characters(): void
    {
        $user = $this->createUser();
        $post = $this->createPost();
        $content = str_repeat('A', 2000);
        $comment = $this->createComment($content, $user, $post);
        
        $this->assertTrue($this->commentManager->validate($comment));
    }

    public function testCommentWithoutAuthor(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'auteur du commentaire est obligatoire.');
        
        $post = $this->createPost();
        $comment = $this->createComment('Commentaire sans auteur', null, $post);
        
        $this->commentManager->validate($comment);
    }

    public function testCommentWithoutPost(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le post du commentaire est obligatoire.');
        
        $user = $this->createUser();
        $comment = $this->createComment('Commentaire sans post', $user, null);
        
        $this->commentManager->validate($comment);
    }

    public function testIsContentValidWithValidContent(): void
    {
        $comment = $this->createComment('Contenu valide');
        
        $this->assertTrue($this->commentManager->isContentValid($comment));
    }

    public function testIsContentValidWithInvalidContent(): void
    {
        $comment = $this->createComment('');
        
        $this->assertFalse($this->commentManager->isContentValid($comment));
    }

    public function testHasAuthorWithAuthor(): void
    {
        $user = $this->createUser();
        $comment = $this->createComment('Test', $user);
        
        $this->assertTrue($this->commentManager->hasAuthor($comment));
    }

    public function testHasAuthorWithoutAuthor(): void
    {
        $comment = $this->createComment('Test');
        
        $this->assertFalse($this->commentManager->hasAuthor($comment));
    }

    public function testHasPostWithPost(): void
    {
        $post = $this->createPost();
        $comment = $this->createComment('Test', null, $post);
        
        $this->assertTrue($this->commentManager->hasPost($comment));
    }

    public function testHasPostWithoutPost(): void
    {
        $comment = $this->createComment('Test');
        
        $this->assertFalse($this->commentManager->hasPost($comment));
    }

    public function testIsAuthorWithCorrectAuthor(): void
    {
        $user = $this->createUser(1);
        $comment = $this->createComment('Test', $user);
        
        $this->assertTrue($this->commentManager->isAuthor($comment, $user));
    }

    public function testIsAuthorWithDifferentUser(): void
    {
        $author = $this->createUser(1);
        $otherUser = $this->createUser(2);
        $comment = $this->createComment('Test', $author);
        
        $this->assertFalse($this->commentManager->isAuthor($comment, $otherUser));
    }

    public function testCanEditAsAuthor(): void
    {
        $user = $this->createUser(1);
        $comment = $this->createComment('Test', $user);
        
        $this->assertTrue($this->commentManager->canEdit($comment, $user));
    }

    public function testCanEditAsOtherUser(): void
    {
        $author = $this->createUser(1);
        $otherUser = $this->createUser(2);
        $comment = $this->createComment('Test', $author);
        
        $this->assertFalse($this->commentManager->canEdit($comment, $otherUser));
    }

    public function testCanDeleteAsCommentAuthor(): void
    {
        $user = $this->createUser(1);
        $post = $this->createPost();
        $comment = $this->createComment('Test', $user, $post);
        
        $this->assertTrue($this->commentManager->canDelete($comment, $user));
    }

    public function testCanDeleteAsPostAuthor(): void
    {
        $postAuthor = $this->createUser(1);
        $commentAuthor = $this->createUser(2);
        $post = $this->createPost($postAuthor);
        $comment = $this->createComment('Test', $commentAuthor, $post);
        
        $this->assertTrue($this->commentManager->canDelete($comment, $postAuthor));
    }

    public function testCanDeleteAsOtherUser(): void
    {
        $commentAuthor = $this->createUser(1);
        $postAuthor = $this->createUser(2);
        $otherUser = $this->createUser(3);
        $post = $this->createPost($postAuthor);
        $comment = $this->createComment('Test', $commentAuthor, $post);
        
        $this->assertFalse($this->commentManager->canDelete($comment, $otherUser));
    }

    public function testIsRecentWithRecentComment(): void
    {
        $comment = $this->createComment('Test');
        $comment->setCreatedAt(new \DateTime('-2 minutes'));
        
        $this->assertTrue($this->commentManager->isRecent($comment, 5));
    }

    public function testIsRecentWithOldComment(): void
    {
        $comment = $this->createComment('Test');
        $comment->setCreatedAt(new \DateTime('-10 minutes'));
        
        $this->assertFalse($this->commentManager->isRecent($comment, 5));
    }
}
