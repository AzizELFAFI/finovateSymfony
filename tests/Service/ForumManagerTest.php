<?php

namespace App\Tests\Service;

use App\Entity\Forum;
use App\Entity\User;
use App\Service\ForumManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le service ForumManager.
 * 
 * Règles métier testées:
 * 1. Le titre doit contenir entre 3 et 200 caractères
 * 2. Le créateur doit être défini
 */
class ForumManagerTest extends TestCase
{
    private ForumManager $forumManager;

    protected function setUp(): void
    {
        $this->forumManager = new ForumManager();
    }

    private function createForum(string $title, ?User $creator = null): Forum
    {
        $forum = new Forum();
        $forum->setTitle($title);
        
        if ($creator !== null) {
            $forum->setCreator($creator);
        }
    
        return $forum;
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

    public function testValidForum(): void
    {
        $user = $this->createUser();
        $forum = $this->createForum('Test Forum', $user);
        
        $this->assertTrue($this->forumManager->validate($forum));
    }

    public function testForumWithShortTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre doit contenir entre 3 et 200 caractères.');
        
        $user = $this->createUser();
        $forum = $this->createForum('AB', $user);
        
        $this->forumManager->validate($forum);
    }

    public function testForumWithLongTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre doit contenir entre 3 et 200 caractères.');
        
        $user = $this->createUser();
        $longTitle = str_repeat('A', 201);
        $forum = $this->createForum($longTitle, $user);
        
        $this->forumManager->validate($forum);
    }

    public function testForumWithExactlyThreeCharactersTitle(): void
    {
        $user = $this->createUser();
        $forum = $this->createForum('ABC', $user);
        
        $this->assertTrue($this->forumManager->validate($forum));
    }

    public function testForumWithExactly200CharactersTitle(): void
    {
        $user = $this->createUser();
        $title = str_repeat('A', 200);
        $forum = $this->createForum($title, $user);
        
        $this->assertTrue($this->forumManager->validate($forum));
    }

    public function testForumWithoutCreator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le créateur du forum est obligatoire.');
        
        $forum = $this->createForum('Test Forum', null);
        
        $this->forumManager->validate($forum);
    }

    public function testIsTitleValidWithValidTitle(): void
    {
        $forum = $this->createForum('Valid Title');
        
        $this->assertTrue($this->forumManager->isTitleValid($forum));
    }

    public function testIsTitleValidWithInvalidTitle(): void
    {
        $forum = $this->createForum('AB');
        
        $this->assertFalse($this->forumManager->isTitleValid($forum));
    }

    public function testHasCreatorWithCreator(): void
    {
        $user = $this->createUser();
        $forum = $this->createForum('Test Forum', $user);
        
        $this->assertTrue($this->forumManager->hasCreator($forum));
    }

    public function testHasCreatorWithoutCreator(): void
    {
        $forum = $this->createForum('Test Forum');
        
        $this->assertFalse($this->forumManager->hasCreator($forum));
    }

    public function testIsCreatorWithCorrectCreator(): void
    {
        $user = $this->createUser(1);
        $forum = $this->createForum('Test Forum', $user);
        
        $this->assertTrue($this->forumManager->isCreator($forum, $user));
    }

    public function testIsCreatorWithDifferentUser(): void
    {
        $creator = $this->createUser(1);
        $otherUser = $this->createUser(2);
        $forum = $this->createForum('Test Forum', $creator);
        
        $this->assertFalse($this->forumManager->isCreator($forum, $otherUser));
    }

    public function testGetPostCount(): void
    {
        $forum = $this->createForum('Test Forum');
        
        $this->assertEquals(0, $this->forumManager->getPostCount($forum));
    }

    public function testGetMemberCount(): void
    {
        $forum = $this->createForum('Test Forum');
        
        $this->assertEquals(0, $this->forumManager->getMemberCount($forum));
    }
}
