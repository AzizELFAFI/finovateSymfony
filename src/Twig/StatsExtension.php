<?php

namespace App\Twig;

use App\Repository\ForumRepository;
use App\Repository\PostRepository;
use App\Repository\CommentRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

/**
 * Bundle Statistiques — injects global stats into all Twig templates.
 * Access via: {{ globalStats.forums }}, {{ globalStats.posts }}, etc.
 * Functions: {{ forum_activity_stats() }}, {{ post_interaction_stats() }}, {{ user_activity_stats() }}
 */
class StatsExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private ForumRepository   $forumRepo,
        private PostRepository    $postRepo,
        private CommentRepository $commentRepo,
        private UserRepository    $userRepo,
        private EntityManagerInterface $em,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('forum_activity_stats', [$this, 'getForumActivityStats'], ['is_safe' => ['html']]),
            new TwigFunction('post_interaction_stats', [$this, 'getPostInteractionStats'], ['is_safe' => ['html']]),
            new TwigFunction('user_activity_stats', [$this, 'getUserActivityStats'], ['is_safe' => ['html']]),
        ];
    }

    public function getGlobals(): array
    {
        return [
            'globalStats' => [
                'forums'   => $this->forumRepo->count([]),
                'posts'    => $this->postRepo->count([]),
                'comments' => $this->commentRepo->count([]),
                'users'    => $this->userRepo->count([]),
            ],
        ];
    }

    /**
     * Statistiques d'activité des forums : Forums avec posts vs Forums sans posts
     */
    public function getForumActivityStats(): string
    {
        $totalForums = $this->forumRepo->count([]);
        
        // Forums avec au moins 1 post
        $forumsWithPosts = $this->em->createQueryBuilder()
            ->select('COUNT(DISTINCT f.id)')
            ->from('App\Entity\Forum', 'f')
            ->innerJoin('f.posts', 'p')
            ->getQuery()
            ->getSingleScalarResult();
            
        $forumsWithoutPosts = $totalForums - $forumsWithPosts;
        
        $withPostsPercentage = $totalForums > 0 ? round(($forumsWithPosts / $totalForums) * 100) : 0;
        $withoutPostsPercentage = 100 - $withPostsPercentage;
        
        return sprintf(
            '<div class="stats-circle forum-activity">
                <div class="circle-chart">
                    <div class="circle-content">
                        <div class="stat-item active">
                            <span class="stat-number">%d%%</span>
                            <span class="stat-label">Avec Posts</span>
                            <span class="stat-count">%d forums</span>
                        </div>
                        <div class="stat-item inactive">
                            <span class="stat-number">%d%%</span>
                            <span class="stat-label">Sans Posts</span>
                            <span class="stat-count">%d forums</span>
                        </div>
                        <div class="total-stat">
                            <span class="total-number">%d</span>
                            <span class="total-label">Total</span>
                        </div>
                    </div>
                </div>
            </div>',
            $withPostsPercentage, $forumsWithPosts,
            $withoutPostsPercentage, $forumsWithoutPosts,
            $totalForums
        );
    }

    /**
     * Statistiques d'interaction des posts : Posts avec commentaires vs Posts sans commentaires
     */
    public function getPostInteractionStats(): string
    {
        $totalPosts = $this->postRepo->count([]);
        
        // Posts avec au moins 1 commentaire
        $postsWithComments = $this->em->createQueryBuilder()
            ->select('COUNT(DISTINCT p.id)')
            ->from('App\Entity\Post', 'p')
            ->innerJoin('p.comments', 'c')
            ->getQuery()
            ->getSingleScalarResult();
            
        $postsWithoutComments = $totalPosts - $postsWithComments;
        
        $withCommentsPercentage = $totalPosts > 0 ? round(($postsWithComments / $totalPosts) * 100) : 0;
        $withoutCommentsPercentage = 100 - $withCommentsPercentage;
        
        return sprintf(
            '<div class="stats-circle post-interaction">
                <div class="circle-chart">
                    <div class="circle-content">
                        <div class="stat-item active">
                            <span class="stat-number">%d%%</span>
                            <span class="stat-label">Interactions</span>
                            <span class="stat-count">%d posts</span>
                        </div>
                        <div class="stat-item inactive">
                            <span class="stat-number">%d%%</span>
                            <span class="stat-label">Sans Réponse</span>
                            <span class="stat-count">%d posts</span>
                        </div>
                        <div class="total-stat">
                            <span class="total-number">%d</span>
                            <span class="total-label">Total</span>
                        </div>
                    </div>
                </div>
            </div>',
            $withCommentsPercentage, $postsWithComments,
            $withoutCommentsPercentage, $postsWithoutComments,
            $totalPosts
        );
    }

    /**
     * Statistiques d'activité des utilisateurs : Utilisateurs actifs vs Utilisateurs inactifs
     */
    public function getUserActivityStats(): string
    {
        $totalUsers = $this->userRepo->count([]);
        
        // Utilisateurs qui ont créé au moins 1 post ou 1 commentaire
        $activeUsers = $this->em->createQueryBuilder()
            ->select('COUNT(DISTINCT u.id)')
            ->from('App\Entity\User', 'u')
            ->leftJoin('App\Entity\Post', 'p', 'WITH', 'p.author = u')
            ->leftJoin('App\Entity\Comment', 'c', 'WITH', 'c.author = u')
            ->where('p.id IS NOT NULL OR c.id IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
            
        $inactiveUsers = $totalUsers - $activeUsers;
        
        $activePercentage = $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100) : 0;
        $inactivePercentage = 100 - $activePercentage;
        
        return sprintf(
            '<div class="stats-circle user-activity">
                <div class="circle-chart">
                    <div class="circle-content">
                        <div class="stat-item active">
                            <span class="stat-number">%d%%</span>
                            <span class="stat-label">Actifs</span>
                            <span class="stat-count">%d users</span>
                        </div>
                        <div class="stat-item inactive">
                            <span class="stat-number">%d%%</span>
                            <span class="stat-label">Inactifs</span>
                            <span class="stat-count">%d users</span>
                        </div>
                        <div class="total-stat">
                            <span class="total-number">%d</span>
                            <span class="total-label">Total</span>
                        </div>
                    </div>
                </div>
            </div>',
            $activePercentage, $activeUsers,
            $inactivePercentage, $inactiveUsers,
            $totalUsers
        );
    }
}