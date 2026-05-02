<?php

namespace App\Tests\Service;

use App\Entity\Forum;
use App\Entity\Post;
use App\Entity\User;
use App\Service\PostManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le service PostManager.
 * 
 * Règles métier testées:
 * 1. Le titre doit contenir entre 3 et 200 caractères
 * 2. Le contenu doit contenir entre 10 et 10000 caractères
 * 3. L'auteur doit être défini
 * 4. Le forum doit être défini
 */
class PostManagerTest extends TestCase
{
    private PostManager $postManager;

    protected function setUp(): void
    {
        $this->postManager = new PostManager();
    }

    private function createPost(string $title, string $content, ?User $author = null, ?Forum $forum = null): Post
    {
        $post = new Post();
        $post->setTitle($title);
        $post->setContent($content);
        
        if ($author !== null) {
            $post->setAuthor($author);
        }
        
        if ($forum !== null) {
            $post->setForum($forum);
        }
        
        return $post;
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

    private function createForum(): Forum
    {
        $forum = new Forum();
        $forum->setTitle('Test Forum');
        
        return $forum;
    }

    public function testValidPost(): void
    {
        $user = $this->createUser();
        $forum = $this->createForum();
        $post = $this->createPost('Test Post', 'Contenu valide avec au moins 10 caractères', $user, $forum);
        
        $this->assertTrue($this->postManager->validate($post));
    }

    public function testPostWithShortTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre doit contenir entre 3 et 200 caractères.');
        
        $user = $this->createUser();
        $forum = $this->createForum();
        $post = $this->createPost('AB', 'Contenu valide avec au moins 10 caractères', $user, $forum);
        
        $this->postManager->validate($post);
    }

    public function testPostWithLongTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre doit contenir entre 3 et 200 caractères.');
        
        $user = $this->createUser();
        $forum = $this->createForum();
        $longTitle = str_repeat('A', 201);
        $post = $this->createPost($longTitle, 'Contenu valide avec au moins 10 caractères', $user, $forum);
        
        $this->postManager->validate($post);
    }

    public function testPostWithExactlyThreeCharactersTitle(): void
    {
        $user = $this->createUser();
        $forum = $this->createForum();
        $post = $this->createPost('ABC', 'Contenu valide avec au moins 10 caractères', $user, $forum);
        
        $this->assertTrue($this->postManager->validate($post));
    }

    public function testPostWithExactly200CharactersTitle(): void
    {
        $user = $this->createUser();
        $forum = $this->createForum();
        $title = str_repeat('A', 200);
        $post = $this->createPost($title, 'Contenu valide avec au moins 10 caractères', $user, $forum);
        
        $this->assertTrue($this->postManager->validate($post));
    }

    public function testPostWithShortContent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le contenu doit contenir au moins 10 caractères.');
        
        $user = $this->createUser();
        $forum = $this->createForum();
        $post = $this->createPost('Test Post', 'Court', $user, $forum);
        
        $this->postManager->validate($post);
    }

    public function testPostWithLongContent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le contenu ne peut pas dépasser 10000 caractères.');
        
        $user = $this->createUser();
        $forum = $this->createForum();
        $longContent = str_repeat('A', 10001);
        $post = $this->createPost('Test Post', $longContent, $user, $forum);
        
        $this->postManager->validate($post);
    }

    public function testPostWithExactly10CharactersContent(): void
    {
        $user = $this->createUser();
        $forum = $this->createForum();
        $post = $this->createPost('Test Post', '1234567890', $user, $forum);
        
        $this->assertTrue($this->postManager->validate($post));
    }

    public function testPostWithExactly10000CharactersContent(): void
    {
        $user = $this->createUser();
        $forum = $this->createForum();
        $content = str_repeat('A', 10000);
        $post = $this->createPost('Test Post', $content, $user, $forum);
        
        $this->assertTrue($this->postManager->validate($post));
    }

    public function testPostWithoutAuthor(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'auteur du post est obligatoire.');
        
        $forum = $this->createForum();
        $post = $this->createPost('Test Post', 'Contenu valide avec au moins 10 caractères', null, $forum);
        
        $this->postManager->validate($post);
    }

    public function testPostWithoutForum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le forum du post est obligatoire.');
        
        $user = $this->createUser();
        $post = $this->createPost('Test Post', 'Contenu valide avec au moins 10 caractères', $user, null);
        
        $this->postManager->validate($post);
    }

    public function testIsTitleValidWithValidTitle(): void
    {
        $post = $this->createPost('Valid Title', 'Content');
        
        $this->assertTrue($this->postManager->isTitleValid($post));
    }

    public function testIsTitleValidWithInvalidTitle(): void
    {
        $post = $this->createPost('AB', 'Content');
        
        $this->assertFalse($this->postManager->isTitleValid($post));
    }

    public function testIsContentValidWithValidContent(): void
    {
        $post = $this->createPost('Title', 'Contenu valide avec au moins 10 caractères');
        
        $this->assertTrue($this->postManager->isContentValid($post));
    }

    public function testIsContentValidWithInvalidContent(): void
    {
        $post = $this->createPost('Title', 'Court');
        
        $this->assertFalse($this->postManager->isContentValid($post));
    }

    public function testHasAuthorWithAuthor(): void
    {
        $user = $this->createUser();
        $post = $this->createPost('Title', 'Content', $user);
        
        $this->assertTrue($this->postManager->hasAuthor($post));
    }

    public function testHasAuthorWithoutAuthor(): void
    {
        $post = $this->createPost('Title', 'Content');
        
        $this->assertFalse($this->postManager->hasAuthor($post));
    }

    public function testHasForumWithForum(): void
    {
        $forum = $this->createForum();
        $post = $this->createPost('Title', 'Content', null, $forum);
        
        $this->assertTrue($this->postManager->hasForum($post));
    }

    public function testHasForumWithoutForum(): void
    {
        $post = $this->createPost('Title', 'Content');
        
        $this->assertFalse($this->postManager->hasForum($post));
    }

    public function testIsAuthorWithCorrectAuthor(): void
    {
        $user = $this->createUser(1);
        $post = $this->createPost('Title', 'Content', $user);
        
        $this->assertTrue($this->postManager->isAuthor($post, $user));
    }

    public function testIsAuthorWithDifferentUser(): void
    {
        $author = $this->createUser(1);
        $otherUser = $this->createUser(2);
        $post = $this->createPost('Title', 'Content', $author);
        
        $this->assertFalse($this->postManager->isAuthor($post, $otherUser));
    }

    public function testCanEditAsAuthor(): void
    {
        $user = $this->createUser(1);
        $post = $this->createPost('Title', 'Content', $user);
        
        $this->assertTrue($this->postManager->canEdit($post, $user));
    }

    public function testCanEditAsOtherUser(): void
    {
        $author = $this->createUser(1);
        $otherUser = $this->createUser(2);
        $post = $this->createPost('Title', 'Content', $author);
        
        $this->assertFalse($this->postManager->canEdit($post, $otherUser));
    }

    public function testCanDeleteAsAuthor(): void
    {
        $user = $this->createUser(1);
        $post = $this->createPost('Title', 'Content', $user);
        
        $this->assertTrue($this->postManager->canDelete($post, $user));
    }

    public function testCanDeleteAsOtherUser(): void
    {
        $author = $this->createUser(1);
        $otherUser = $this->createUser(2);
        $post = $this->createPost('Title', 'Content', $author);
        
        $this->assertFalse($this->postManager->canDelete($post, $otherUser));
    }

    public function testGetCommentCount(): void
    {
        $post = $this->createPost('Title', 'Content');
        
        $this->assertEquals(0, $this->postManager->getCommentCount($post));
    }

    public function testGetUpvoteCount(): void
    {
        $post = $this->createPost('Title', 'Content');
        
        $this->assertEquals(0, $this->postManager->getUpvoteCount($post));
    }

    public function testGetDownvoteCount(): void
    {
        $post = $this->createPost('Title', 'Content');
        
        $this->assertEquals(0, $this->postManager->getDownvoteCount($post));
    }

    public function testGetNetVotes(): void
    {
        $post = $this->createPost('Title', 'Content');
        
        $this->assertEquals(0, $this->postManager->getNetVotes($post));
    }
}
