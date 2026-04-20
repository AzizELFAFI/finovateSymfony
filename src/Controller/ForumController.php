<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Forum;
use App\Entity\Post;
use App\Entity\SharedPost;
use App\Entity\UserForum;
use App\Entity\Vote;
use App\Repository\AlertRepository;
use App\Repository\ForumRecommendationRepository;
use App\Repository\ForumRepository;
use App\Repository\PostRepository;
use App\Repository\SharedPostRepository;
use App\Repository\UserBadgeRepository;
use App\Repository\UserForumRepository;
use App\Repository\VoteRepository;
use App\Repository\CommentRepository;
use App\Service\AlertService;
use App\Service\AiService;
use App\Service\AdminRestrictionService;
use App\Service\InteractionService;
use App\Service\RecommendationService;
use App\Service\UserRelationService;
use App\Repository\UserBlockRepository;
use App\Repository\UserPeerRestrictionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/forum')]
final class ForumController extends AbstractController
{
    private function getCurrentUserId(): int
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            throw $this->createAccessDeniedException('Not authenticated.');
        }
        return (int) $user->getId();
    }

    private function getCurrentUser(EntityManagerInterface $em): \App\Entity\User
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            throw $this->createAccessDeniedException('Not authenticated.');
        }
        return $em->find(\App\Entity\User::class, $user->getId());
    }

    private function checkRestriction(string $action, AdminRestrictionService $restrictionService, EntityManagerInterface $em): ?Response
    {
        $user = $em->find(\App\Entity\User::class, $this->getCurrentUserId());
        if (!$user) return null;
        if ($restrictionService->isRestricted($user, $action)) {
            $msg = match($action) {
                'post'         => '🚫 Vous êtes restreint et ne pouvez pas publier de posts.',
                'comment'      => '🚫 Vous êtes restreint et ne pouvez pas commenter.',
                'create_forum' => '🚫 Vous êtes restreint et ne pouvez pas créer de forums.',
                default        => '🚫 Action non autorisée.',
            };
            $this->addFlash('error', $msg);
            return $this->redirectToRoute('app_forum_home');
        }
        return null;
    }

    private function checkPeerRestriction(?int $targetUserId, UserRelationService $relations, string $redirectRoute, array $redirectParams = []): ?Response
    {
        if (!$targetUserId) return null;
        if ($relations->isPeerRestricted($targetUserId, $this->getCurrentUserId())) {
            $this->addFlash('error', '🚫 Vous êtes restreint par cet utilisateur.');
            return $this->redirectToRoute($redirectRoute, $redirectParams);
        }
        return null;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function downloadRemoteImage(string $url): ?string
    {
        try {
            // Use file_get_contents with a timeout context
            $ctx = stream_context_create(['http' => ['timeout' => 15]]);
            $content = @file_get_contents($url, false, $ctx);
            if (!$content || strlen($content) < 100) return null;

            // Detect extension from content
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->buffer($content);
            $ext   = match($mime) {
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
                'image/gif'  => 'gif',
                default      => 'jpg',
            };

            $filename = 'ai_' . uniqid() . '.' . $ext;
            $path     = $this->getParameter('kernel.project_dir') . '/public/uploads/images/' . $filename;
            file_put_contents($path, $content);
            return 'uploads/images/' . $filename;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function uploadImage(Request $request, string $field, SluggerInterface $slugger, \App\Service\CloudinaryService $cloudinary): ?string
    {
        $file = $request->files->get($field);
        if (!$file) return null;

        // Try Cloudinary first
        try {
            $cloudinaryUrl = $cloudinary->upload($file, 'finovate/forum');
            return $cloudinaryUrl;
        } catch (\Throwable $e) {
            error_log('Cloudinary upload failed in ForumController: ' . $e->getMessage());
            // Fallback to local
        }

        // Fallback: Local upload
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $ext;
        try {
            $file->move($this->getParameter('kernel.project_dir') . '/public/uploads/images', $newFilename);
            return 'uploads/images/' . $newFilename;
        } catch (FileException $e) {
            return null;
        }
    }

    private function getUser18(EntityManagerInterface $em): mixed
    {
        return $em->getReference(\App\Entity\User::class, $this->getCurrentUserId());
    }

    // ── Forum pages ───────────────────────────────────────────────────────────

    #[Route('', name: 'app_forum_home')]
    public function home(
        ForumRepository $forumRepo,
        UserForumRepository $ufRepo,
        \App\Repository\PostRepository $postRepo,
        \App\Repository\UserBadgeRepository $badgeRepo,
        Request $request
    ): Response {
        $search = $request->query->get('q', '');
        $sort   = $request->query->get('sort', 'recent');
        $page   = max(1, (int) $request->query->get('page', 1));
        $limit  = 12; // Forums per page

        $qb = $forumRepo->createQueryBuilder('f');
        if ($search) {
            $qb->andWhere('f.title LIKE :q OR f.description LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }
        match ($sort) {
            'oldest'   => $qb->orderBy('f.createdAt', 'ASC'),
            'popular'  => $qb->leftJoin('f.posts', 'fp')->leftJoin('fp.votes', 'fv')->groupBy('f.id')->orderBy('COUNT(fv.id)', 'DESC'),
            'posts'    => $qb->leftJoin('f.posts', 'p')->groupBy('f.id')->orderBy('COUNT(p.id)', 'DESC'),
            default    => $qb->orderBy('f.createdAt', 'DESC'),
        };

        // Get total count for pagination
        $totalQb = clone $qb;
        $total = (int) $totalQb->select('COUNT(DISTINCT f.id)')->getQuery()->getSingleScalarResult();
        
        // Apply pagination
        $forums = $qb->setFirstResult(($page - 1) * $limit)
                    ->setMaxResults($limit)
                    ->getQuery()->getResult();

        $joinedIds = [];
        $joinedForumsList = [];
        
        if ($this->getUser()) {
            $joinedIds = array_map(
                fn($uf) => $uf->getForum()->getId(),
                $ufRepo->createQueryBuilder('uf')->where('uf.user = :uid')->setParameter('uid', $this->getCurrentUserId())->getQuery()->getResult()
            );
            
            // Horizontal strips: joined forums + recommended (most posts)
            $joinedForums = $ufRepo->createQueryBuilder('uf')
                ->where('uf.user = :uid')->setParameter('uid', $this->getCurrentUserId())
                ->getQuery()->getResult();
            $joinedForumsList = array_map(fn($uf) => $uf->getForum(), $joinedForums);
        }
        $weekAgo = new \DateTimeImmutable('-7 days');
        $recentWeek = $forumRepo->createQueryBuilder('f')
            ->where('f.createdAt >= :w')->setParameter('w', $weekAgo)
            ->orderBy('f.createdAt', 'DESC')->setMaxResults(5)->getQuery()->getResult();
        if (count($recentWeek) < 5) {
            $recentWeek = $forumRepo->createQueryBuilder('f')->orderBy('f.createdAt', 'DESC')->setMaxResults(5)->getQuery()->getResult();
        }
        $recentWeekIds = array_map(fn($f) => $f->getId(), $recentWeek);

        // 5 most popular (by votes)
        $popular = $forumRepo->createQueryBuilder('f')
            ->leftJoin('f.posts', 'p')->leftJoin('p.votes', 'v')
            ->groupBy('f.id')->orderBy('COUNT(v.id)', 'DESC')->setMaxResults(5)->getQuery()->getResult();
        $popularIds = array_map(fn($f) => $f->getId(), $popular);

        // Remaining forums (not in recent or popular) - only for first page
        $remainingFirst = $remainingSecond = [];
        if ($page === 1) {
            $usedIds = array_unique(array_merge($recentWeekIds, $popularIds));
            $remaining = array_filter($forums, fn($f) => !in_array($f->getId(), $usedIds));
            $remaining = array_values($remaining);
            $half = (int) ceil(count($remaining) / 2);
            $remainingFirst  = array_slice($remaining, 0, $half);
            $remainingSecond = array_slice($remaining, $half);
        }

        $recommended = $forumRepo->createQueryBuilder('f')
            ->leftJoin('f.posts', 'p')->groupBy('f.id')->orderBy('COUNT(p.id)', 'DESC')
            ->setMaxResults(10)->getQuery()->getResult();

        // Creator of the week: user with most badges this week
        $creatorOfWeek = null;
        $creatorPosts  = [];
        $allBadges = $badgeRepo->createQueryBuilder('ub')
            ->where('ub.earnedAt >= :w')->setParameter('w', $weekAgo)
            ->getQuery()->getResult();
        $badgeCounts = [];
        foreach ($allBadges as $ub) {
            $uid = $ub->getUser()->getId();
            $badgeCounts[$uid] = ($badgeCounts[$uid] ?? 0) + 1;
        }
        if ($badgeCounts) {
            arsort($badgeCounts);
            $topUserId = array_key_first($badgeCounts);
            $creatorPosts = $postRepo->createQueryBuilder('p')
                ->leftJoin('p.votes', 'v')->where('p.author = :uid')->setParameter('uid', $topUserId)
                ->groupBy('p.id')->orderBy('COUNT(v.id)', 'DESC')->setMaxResults(3)->getQuery()->getResult();
            if ($creatorPosts) $creatorOfWeek = $creatorPosts[0]->getAuthor();
        }

        // Determine which template to render based on user login status
        $template = $this->getUser() ? 'forum/forums_home.html.twig' : 'forum/forums_home_guest.html.twig';

        return $this->render($template, [
            'forums'          => $forums,
            'joinedIds'       => $joinedIds,
            'search'          => $search,
            'sort'            => $sort,
            'page'            => $page,
            'pages'           => (int) ceil($total / $limit),
            'total'           => $total,
            'recentWeek'      => $recentWeek,
            'popular'         => $popular,
            'remainingFirst'  => $remainingFirst,
            'remainingSecond' => $remainingSecond,
            'joinedForumsList'=> $joinedForumsList,
            'recommended'     => $recommended,
            'creatorOfWeek'   => $creatorOfWeek,
            'creatorPosts'    => $creatorPosts,
        ]);
    }

    #[Route('/my-forums', name: 'app_forum_my')]
    public function myForums(ForumRepository $forumRepo): Response
    {
        $forums = $forumRepo->createQueryBuilder('f')
            ->where('f.creator = :uid')->setParameter('uid', $this->getCurrentUserId())
            ->getQuery()->getResult();
        return $this->render('forum/forums.html.twig', ['forums' => $forums]);
    }

    #[Route('/joined', name: 'app_forum_joined')]
    public function joinedForums(UserForumRepository $userForumRepo): Response
    {
        $memberships = $userForumRepo->createQueryBuilder('uf')
            ->where('uf.user = :uid')->setParameter('uid', $this->getCurrentUserId())
            ->getQuery()->getResult();
        return $this->render('forum/joined_forums.html.twig', ['memberships' => $memberships]);
    }

    #[Route('/shared', name: 'app_forum_shared')]
    public function sharedPosts(SharedPostRepository $sharedPostRepo): Response
    {
        $sharedPosts = $sharedPostRepo->createQueryBuilder('sp')
            ->where('sp.user = :uid')->setParameter('uid', $this->getCurrentUserId())
            ->orderBy('sp.sharedAt', 'DESC')
            ->getQuery()->getResult();
        return $this->render('forum/shared.html.twig', ['sharedPosts' => $sharedPosts]);
    }

    #[Route('/badges', name: 'app_forum_badges')]
    public function badges(UserBadgeRepository $userBadgeRepo): Response
    {
        $badges = $userBadgeRepo->createQueryBuilder('ub')
            ->where('ub.user = :uid')->setParameter('uid', $this->getCurrentUserId())
            ->getQuery()->getResult();
        return $this->render('forum/badges_view.html.twig', ['badges' => $badges]);
    }

    // ── Forum CRUD ────────────────────────────────────────────────────────────

    #[Route('/create-forum', name: 'app_forum_create', methods: ['GET', 'POST'])]
    public function createForum(Request $request, EntityManagerInterface $em, SluggerInterface $slugger, AdminRestrictionService $restrictionService, \App\Service\CloudinaryService $cloudinary): Response
    {
        if ($redirect = $this->checkRestriction('create_forum', $restrictionService, $em)) return $redirect;
        if ($request->isMethod('POST')) {
            $forum = new Forum();
            $forum->setTitle($request->request->get('title'));
            $forum->setDescription($request->request->get('description'));
            $forum->setCreator($this->getCurrentUser($em));
            $imageUrl = $this->uploadImage($request, 'image', $slugger, $cloudinary);
            if ($imageUrl) $forum->setImageUrl($imageUrl);
            $em->persist($forum);
            $em->flush();
            return $this->redirectToRoute('app_forum_my');
        }
        return $this->render('forum/create_forum_dialog.html.twig');
    }

    #[Route('/{id}/edit-forum', name: 'app_forum_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editForum(Forum $forum, Request $request, EntityManagerInterface $em, SluggerInterface $slugger, \App\Service\CloudinaryService $cloudinary): Response
    {
        if ($request->isMethod('POST')) {
            $forum->setTitle($request->request->get('title'));
            $forum->setDescription($request->request->get('description'));
            $imageUrl = $this->uploadImage($request, 'image', $slugger, $cloudinary);
            if ($imageUrl) $forum->setImageUrl($imageUrl);
            $em->flush();
            return $this->redirectToRoute('app_forum_my');
        }
        return $this->render('forum/edit_forum_dialog.html.twig', ['forum' => $forum]);
    }

    #[Route('/{id}/delete-forum', name: 'app_forum_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteForum(Forum $forum, EntityManagerInterface $em): Response
    {
        // Remove posts and their children first to avoid FK constraint violations
        foreach ($forum->getPosts() as $post) {
            foreach ($post->getComments() as $comment) {
                $em->remove($comment);
            }
            foreach ($post->getVotes() as $vote) {
                $em->remove($vote);
            }
            foreach ($post->getSharedPosts() as $shared) {
                $em->remove($shared);
            }
            $em->remove($post);
        }
        // Remove memberships
        foreach ($forum->getMembers() as $member) {
            $em->remove($member);
        }
        $em->flush();
        $em->remove($forum);
        $em->flush();
        return $this->redirectToRoute('app_forum_my');
    }

    #[Route('/{id}/join', name: 'app_forum_join', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function joinForum(Forum $forum, EntityManagerInterface $em, UserForumRepository $ufRepo, AlertService $alerts, UserRelationService $relations, InteractionService $interactions): Response
    {
        // Check if forum creator has peer-restricted me
        if ($forum->getCreator() && ($r = $this->checkPeerRestriction($forum->getCreator()->getId(), $relations, 'app_forum_home'))) return $r;
        $existing = $ufRepo->createQueryBuilder('uf')
            ->where('uf.forum = :f AND uf.user = :uid')
            ->setParameter('f', $forum)->setParameter('uid', $this->getCurrentUserId())
            ->getQuery()->getOneOrNullResult();
        if (!$existing) {
            $uf = new UserForum();
            $uf->setForum($forum);
            $uf->setUser($this->getCurrentUser($em));
            $em->persist($uf);

            // Alert forum creator
            if ($forum->getCreator()) {
                $alerts->create(
                    $forum->getCreator()->getId(),
                    \App\Entity\Alert::TYPE_JOIN,
                    '👥 Quelqu\'un a rejoint votre forum "' . $forum->getTitle() . '"',
                    '/forum/' . $forum->getId() . '/posts'
                );
            }
            $em->flush();
            $newBadges = $alerts->checkAndAwardBadges($this->getCurrentUserId());
            $em->flush();
            if ($newBadges) {
                $this->addFlash('new_badges', json_encode($newBadges));
            }

            // Track JOIN interaction (after flush so membership exists)
            try { $interactions->track($this->getCurrentUserId(), $forum->getId(), 'JOIN'); } catch (\Throwable) {}
        }
        return $this->redirectToRoute('app_forum_joined');
    }

    #[Route('/{id}/leave', name: 'app_forum_leave', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function leaveForum(Forum $forum, EntityManagerInterface $em, UserForumRepository $ufRepo, AlertService $alerts): Response
    {
        $membership = $ufRepo->createQueryBuilder('uf')
            ->where('uf.forum = :f AND uf.user = :uid')
            ->setParameter('f', $forum)->setParameter('uid', $this->getCurrentUserId())
            ->getQuery()->getOneOrNullResult();
        if ($membership) {
            $em->remove($membership);
            if ($forum->getCreator()) {
                $alerts->create(
                    $forum->getCreator()->getId(),
                    \App\Entity\Alert::TYPE_LEAVE,
                    '👋 Quelqu\'un a quitté votre forum "' . $forum->getTitle() . '"',
                    '/forum/' . $forum->getId() . '/posts'
                );
            }
            $em->flush();
        }
        return $this->redirectToRoute('app_forum_joined');
    }

    // ── Post pages ────────────────────────────────────────────────────────────

    #[Route('/{id}/posts', name: 'app_forum_posts', requirements: ['id' => '\d+'])]
    public function posts(Forum $forum, PostRepository $postRepo, Request $request, UserBlockRepository $blockRepo, InteractionService $interactions): Response
    {
        // Track VIEW interaction
        if ($this->getUser()) {
            try { $interactions->track($this->getCurrentUserId(), $forum->getId(), 'VIEW'); } catch (\Throwable) {}
        }
        $sort   = $request->query->get('sort', 'recent');
        $search = $request->query->get('q', '');

        $hiddenIds = [];
        if ($this->getUser()) {
            $hiddenIds = $blockRepo->findHiddenIds($this->getCurrentUserId());
        }

        $qb = $postRepo->createQueryBuilder('p')
            ->where('p.forum = :forum')
            ->setParameter('forum', $forum);

        if ($hiddenIds) {
            $qb->andWhere('p.author IS NULL OR p.author NOT IN (:hidden)')
               ->setParameter('hidden', $hiddenIds);
        }

        if ($search) {
            $qb->andWhere('p.title LIKE :q OR p.content LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }

        match ($sort) {
            'oldest'    => $qb->orderBy('p.createdAt', 'ASC'),
            'popular'   => $qb->leftJoin('p.votes', 'v')->groupBy('p.id')->orderBy('COUNT(v.id)', 'DESC'),
            'commented' => $qb->leftJoin('p.comments', 'c')->groupBy('p.id')->orderBy('COUNT(c.id)', 'DESC'),
            default     => $qb->orderBy('p.createdAt', 'DESC'),
        };

        // Determine which template to render based on user login status
        $template = $this->getUser() ? 'forum/posts.html.twig' : 'forum/posts_guest.html.twig';

        return $this->render($template, [
            'forum'  => $forum,
            'posts'  => $qb->getQuery()->getResult(),
            'sort'   => $sort,
            'search' => $search,
        ]);
    }

    #[Route('/post/{id}', name: 'app_post_detail', requirements: ['id' => '\d+'])]
    public function postDetail(Post $post, VoteRepository $voteRepo, UserForumRepository $ufRepo, ForumRepository $forumRepo, UserBlockRepository $blockRepo, UserPeerRestrictionRepository $restrictRepo, UserRelationService $relations, InteractionService $interactions): Response
    {
        // Track CLICK interaction on the forum this post belongs to
        if ($this->getUser() && $post->getForum()) {
            try { $interactions->track($this->getCurrentUserId(), $post->getForum()->getId(), 'CLICK'); } catch (\Throwable) {}
        }
        $authorId = $post->getAuthor()?->getId();

        $userVote = $voteRepo->createQueryBuilder('v')
            ->where('v.post = :p AND v.user = :uid')
            ->setParameter('p', $post)->setParameter('uid', $this->getCurrentUserId())
            ->getQuery()->getOneOrNullResult();

        // Forums user can share to (created + joined)
        $myForums = $forumRepo->createQueryBuilder('f')
            ->where('f.creator = :uid')->setParameter('uid', $this->getCurrentUserId())
            ->getQuery()->getResult();
        $joinedForums = array_map(
            fn($uf) => $uf->getForum(),
            $ufRepo->createQueryBuilder('uf')->where('uf.user = :uid')->setParameter('uid', $this->getCurrentUserId())->getQuery()->getResult()
        );
        $shareForums = array_unique(array_merge($myForums, $joinedForums), SORT_REGULAR);

        // Filter comments from blocked users
        $hiddenIds = $blockRepo->findHiddenIds($this->getCurrentUserId());
        $comments  = array_filter(
            $post->getComments()->toArray(),
            fn($c) => !$c->getAuthor() || !in_array($c->getAuthor()->getId(), $hiddenIds)
        );

        // Is author peer-restricted by me?
        $isAuthorRestricted = $authorId ? $relations->isPeerRestricted($this->getCurrentUserId(), $authorId) : false;
        $isAuthorBlocked    = $authorId ? $relations->isBlocked($this->getCurrentUserId(), $authorId) : false;

        return $this->render('forum/post_details.html.twig', [
            'post'               => $post,
            'userVote'           => $userVote,
            'shareForums'        => $shareForums,
            'filteredComments'   => array_values($comments),
            'isAuthorBlocked'    => $isAuthorBlocked,
            'isAuthorRestricted' => $isAuthorRestricted,
        ]);
    }

    // ── Post CRUD ─────────────────────────────────────────────────────────────

    #[Route('/{id}/create-post', name: 'app_post_create', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function createPost(Forum $forum, Request $request, EntityManagerInterface $em, SluggerInterface $slugger, AlertService $alerts, AdminRestrictionService $restrictionService, \App\Service\CloudinaryService $cloudinary, InteractionService $interactions): Response
    {
        if ($redirect = $this->checkRestriction('post', $restrictionService, $em)) return $redirect;
        if ($request->isMethod('POST')) {
            $post = new Post();
            $post->setTitle($request->request->get('title'));
            $post->setContent($request->request->get('content'));
            $post->setForum($forum);
            $post->setAuthor($this->getCurrentUser($em));
            $imageUrl = $this->uploadImage($request, 'image', $slugger, $cloudinary);

            // Fallback: use AI-generated image (already saved locally)
            if (!$imageUrl) {
                $aiUrl = trim($request->request->get('ai_image_url', ''));
                if ($aiUrl) {
                    // If it's a local path like /uploads/images/xxx.jpg — use directly
                    if (str_starts_with($aiUrl, '/uploads/') || str_starts_with($aiUrl, 'uploads/')) {
                        $imageUrl = ltrim($aiUrl, '/');
                    } else {
                        // External URL — download it
                        $imageUrl = $this->downloadRemoteImage($aiUrl);
                    }
                }
            }

            if ($imageUrl) $post->setImageUrl($imageUrl);
            $em->persist($post);

            // Alert forum creator about new post
            if ($forum->getCreator() && $forum->getCreator()->getId() !== $this->getCurrentUserId()) {
                $alerts->create(
                    $forum->getCreator()->getId(),
                    \App\Entity\Alert::TYPE_NEW_POST,
                    '📝 Nouveau post dans votre forum "' . $forum->getTitle() . '" : ' . $post->getTitle()
                );
            }
            $em->flush();
            $newBadges = $alerts->checkAndAwardBadges($this->getCurrentUserId());
            $em->flush();
            if ($newBadges) $this->addFlash('new_badges', json_encode($newBadges));

            // Track POST interaction
            try { $interactions->track($this->getCurrentUserId(), $forum->getId(), 'POST'); } catch (\Throwable) {}

            return $this->redirectToRoute('app_forum_posts', ['id' => $forum->getId()]);
        }
        return $this->render('forum/create_post_dialog.html.twig', ['forum' => $forum]);
    }

    #[Route('/post/{id}/edit', name: 'app_post_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editPost(Post $post, Request $request, EntityManagerInterface $em, SluggerInterface $slugger, \App\Service\CloudinaryService $cloudinary): Response
    {
        if ($request->isMethod('POST')) {
            $post->setTitle($request->request->get('title'));
            $post->setContent($request->request->get('content'));
            $post->setUpdatedAt(new \DateTime());
            $imageUrl = $this->uploadImage($request, 'image', $slugger, $cloudinary);
            if ($imageUrl) $post->setImageUrl($imageUrl);
            $em->flush();
            return $this->redirectToRoute('app_post_detail', ['id' => $post->getId()]);
        }
        return $this->render('forum/edit_post_dialog.html.twig', ['post' => $post]);
    }

    #[Route('/post/{id}/delete', name: 'app_post_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deletePost(Post $post, EntityManagerInterface $em): Response
    {
        $forumId = $post->getForum()?->getId();
        $em->remove($post);
        $em->flush();
        return $forumId
            ? $this->redirectToRoute('app_forum_posts', ['id' => $forumId])
            : $this->redirectToRoute('app_forum_home');
    }

    // ── Vote ──────────────────────────────────────────────────────────────────

    #[Route('/post/{id}/report', name: 'app_post_report', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reportPost(Post $post, Request $request, EntityManagerInterface $em): Response
    {
        $reason  = $request->request->get('reason', '');
        $details = $request->request->get('details', '');
        if (!$reason) return $this->redirectToRoute('app_post_detail', ['id' => $post->getId()]);

        $report = new \App\Entity\UserReport();
        $report->setPost($post);
        $report->setReporter($this->getCurrentUser($em));
        $report->setReason($reason);
        if ($details) $report->setDetails($details);
        $em->persist($report);
        $em->flush();

        $this->addFlash('success', '✅ Signalement envoyé. Merci pour votre contribution.');
        return $this->redirectToRoute('app_post_detail', ['id' => $post->getId()]);
    }

    #[Route('/post/{id}/vote', name: 'app_post_vote', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function vote(Post $post, Request $request, EntityManagerInterface $em, VoteRepository $voteRepo, AlertService $alerts, UserRelationService $relations, InteractionService $interactions): Response
    {
        if ($post->getAuthor() && ($r = $this->checkPeerRestriction($post->getAuthor()->getId(), $relations, 'app_post_detail', ['id' => $post->getId()]))) return $r;
        $type = $request->request->get('type');
        $existing = $voteRepo->createQueryBuilder('v')
            ->where('v.post = :p AND v.user = :uid')
            ->setParameter('p', $post)->setParameter('uid', $this->getCurrentUserId())
            ->getQuery()->getOneOrNullResult();

        if ($existing) {
            if ($existing->getVoteType() === $type) {
                $em->remove($existing);
            } else {
                $existing->setVoteType($type);
            }
        } else {
            $vote = new Vote();
            $vote->setPost($post);
            $vote->setUser($this->getCurrentUser($em));
            $vote->setVoteType($type);
            $em->persist($vote);

            // Alert post author
            if ($post->getAuthor() && $post->getAuthor()->getId() !== $this->getCurrentUserId()) {
                $emoji = $type === 'UPVOTE' ? '👍' : '👎';
                $alerts->create(
                    $post->getAuthor()->getId(),
                    \App\Entity\Alert::TYPE_VOTE,
                    $emoji . ' Quelqu\'un a vote sur votre post "' . $post->getTitle() . '"',
                    '/forum/post/' . $post->getId()
                );
            }
        }
        $em->flush();
        $newBadges = $alerts->checkAndAwardBadges($this->getCurrentUserId());
        $em->flush();
        if ($newBadges) $this->addFlash('new_badges', json_encode($newBadges));

        // Track VOTE interaction
        if ($post->getForum()) {
            try { $interactions->track($this->getCurrentUserId(), $post->getForum()->getId(), 'VOTE'); } catch (\Throwable) {}
        }

        return $this->redirectToRoute('app_post_detail', ['id' => $post->getId()]);
    }

    // ── Share ─────────────────────────────────────────────────────────────────

    #[Route('/post/{id}/share', name: 'app_post_share', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function sharePost(Post $post, Request $request, EntityManagerInterface $em, SharedPostRepository $spRepo, AlertService $alerts, ForumRepository $forumRepo, UserRelationService $relations, InteractionService $interactions): Response
    {
        if ($post->getAuthor() && ($r = $this->checkPeerRestriction($post->getAuthor()->getId(), $relations, 'app_post_detail', ['id' => $post->getId()]))) return $r;
        $targetForumId = $request->request->get('target_forum_id');
        $comment       = trim($request->request->get('comment', ''));

        if (!$targetForumId) {
            $this->addFlash('error', 'Veuillez choisir un forum.');
            return $this->redirectToRoute('app_post_detail', ['id' => $post->getId()]);
        }

        $targetForum = $forumRepo->find($targetForumId);
        if (!$targetForum) {
            return $this->redirectToRoute('app_post_detail', ['id' => $post->getId()]);
        }

        // Prevent duplicate shares to same forum
        $existing = $spRepo->createQueryBuilder('sp')
            ->where('sp.post = :p AND sp.user = :uid AND sp.targetForum = :f')
            ->setParameter('p', $post)->setParameter('uid', $this->getCurrentUserId())->setParameter('f', $targetForum)
            ->getQuery()->getOneOrNullResult();

        if (!$existing) {
            // Create a real Post in the target forum (shared post appears in forum feed)
            $sharedPostEntry = new Post();
            $originalAuthor  = $post->getAuthor() ? $post->getAuthor()->getFirstname() . ' ' . $post->getAuthor()->getLastname() : 'Anonyme';
            $sharedTitle     = '🔁 [Partagé] ' . $post->getTitle();
            $sharedContent   = ($comment ? "💬 " . $comment . "\n\n" : '') .
                               "─── Post original de " . $originalAuthor . " ───\n\n" .
                               $post->getContent();
            $sharedPostEntry->setTitle($sharedTitle);
            $sharedPostEntry->setContent($sharedContent);
            $sharedPostEntry->setForum($targetForum);
            $sharedPostEntry->setAuthor($this->getCurrentUser($em));
            if ($post->getImageUrl()) $sharedPostEntry->setImageUrl($post->getImageUrl());
            $em->persist($sharedPostEntry);

            // Also track in SharedPost for history
            $shared = new SharedPost();
            $shared->setPost($post);
            $shared->setUser($this->getCurrentUser($em));
            $shared->setTargetForum($targetForum);
            if ($comment) $shared->setComment($comment);
            $em->persist($shared);

            if ($post->getAuthor() && $post->getAuthor()->getId() !== $this->getCurrentUserId()) {
                $alerts->create(
                    $post->getAuthor()->getId(),
                    \App\Entity\Alert::TYPE_SHARE,
                    '📤 Votre post "' . $post->getTitle() . '" a été partagé dans "' . $targetForum->getTitle() . '"',
                    '/forum/post/' . $post->getId()
                );
            }
            $em->flush();
            $newBadges = $alerts->checkAndAwardBadges($this->getCurrentUserId());
            $em->flush();
            if ($newBadges) $this->addFlash('new_badges', json_encode($newBadges));
            $this->addFlash('success', 'Post partagé dans "' . $targetForum->getTitle() . '" !');

            // Track SHARE interaction on the original post's forum
            if ($post->getForum()) {
                try { $interactions->track($this->getCurrentUserId(), $post->getForum()->getId(), 'SHARE'); } catch (\Throwable) {}
            }
        }

        return $this->redirectToRoute('app_forum_posts', ['id' => $targetForum->getId()]);
    }

    #[Route('/shared/{id}/delete', name: 'app_shared_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteShared(SharedPost $sharedPost, EntityManagerInterface $em): Response
    {
        $em->remove($sharedPost);
        $em->flush();
        return $this->redirectToRoute('app_forum_shared');
    }

    // ── Comment CRUD ──────────────────────────────────────────────────────────

    #[Route('/post/{id}/comment', name: 'app_post_comment', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addComment(Post $post, Request $request, EntityManagerInterface $em, AlertService $alerts, AdminRestrictionService $restrictionService, UserRelationService $relations, InteractionService $interactions): Response
    {
        if ($redirect = $this->checkRestriction('comment', $restrictionService, $em)) return $redirect;
        if ($post->getAuthor() && ($r = $this->checkPeerRestriction($post->getAuthor()->getId(), $relations, 'app_post_detail', ['id' => $post->getId()]))) return $r;
        $content = trim($request->request->get('content', ''));
        if ($content) {
            $comment = new Comment();
            $comment->setContent($content);
            $comment->setPost($post);
            $comment->setAuthor($this->getCurrentUser($em));
            $em->persist($comment);

            if ($post->getAuthor() && $post->getAuthor()->getId() !== $this->getCurrentUserId()) {
                $alerts->create(
                    $post->getAuthor()->getId(),
                    \App\Entity\Alert::TYPE_COMMENT,
                    '💬 Nouveau commentaire sur votre post "' . $post->getTitle() . '"',
                    '/forum/post/' . $post->getId()
                );
            }
            $em->flush();
            $newBadges = $alerts->checkAndAwardBadges($this->getCurrentUserId());
            $em->flush();
            if ($newBadges) $this->addFlash('new_badges', json_encode($newBadges));

            // Track COMMENT interaction
            if ($post->getForum()) {
                try { $interactions->track($this->getCurrentUserId(), $post->getForum()->getId(), 'COMMENT'); } catch (\Throwable) {}
            }
        }
        return $this->redirectToRoute('app_post_detail', ['id' => $post->getId()]);
    }

    #[Route('/comment/{id}/edit', name: 'app_comment_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function editComment(Comment $comment, Request $request, EntityManagerInterface $em): Response
    {
        $content = trim($request->request->get('content', ''));
        if ($content) {
            $comment->setContent($content);
            $comment->setUpdatedAt(new \DateTime());
            $em->flush();
        }
        return $this->redirectToRoute('app_post_detail', ['id' => $comment->getPost()->getId()]);
    }

    #[Route('/comment/{id}/delete', name: 'app_comment_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteComment(Comment $comment, EntityManagerInterface $em): Response
    {
        $postId = $comment->getPost()->getId();
        $em->remove($comment);
        $em->flush();
        return $this->redirectToRoute('app_post_detail', ['id' => $postId]);
    }

    // ── Block / Restrict ──────────────────────────────────────────────────────

    #[Route('/user/{id}/block', name: 'app_user_block', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function blockUser(int $id, Request $request, EntityManagerInterface $em, UserRelationService $relations): Response
    {
        $me     = $em->find(\App\Entity\User::class, $this->getCurrentUserId());
        $target = $em->find(\App\Entity\User::class, $id);
        if (!$me || !$target || $me->getId() === $target->getId()) {
            return $this->redirectToRoute('app_forum_home');
        }
        $reason = trim($request->request->get('reason', ''));
        $relations->block($me, $target, $reason);
        $this->addFlash('success', '🚫 Utilisateur bloqué.');
        return $this->redirectToRoute('app_forum_home');
    }

    #[Route('/user/{id}/unblock', name: 'app_user_unblock', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function unblockUser(int $id, EntityManagerInterface $em, UserRelationService $relations): Response
    {
        $me     = $em->find(\App\Entity\User::class, $this->getCurrentUserId());
        $target = $em->find(\App\Entity\User::class, $id);
        if ($me && $target) {
            $relations->unblock($me, $target);
            $this->addFlash('success', '✅ Utilisateur débloqué.');
        }
        return $this->redirectToRoute('app_forum_blocked_users');
    }

    #[Route('/user/{id}/restrict', name: 'app_user_restrict', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function restrictUser(int $id, Request $request, EntityManagerInterface $em, UserRelationService $relations): Response
    {
        $me     = $em->find(\App\Entity\User::class, $this->getCurrentUserId());
        $target = $em->find(\App\Entity\User::class, $id);
        if (!$me || !$target || $me->getId() === $target->getId()) {
            return $this->redirectToRoute('app_forum_home');
        }
        $reason = trim($request->request->get('reason', ''));
        $days   = $request->request->get('days', '') !== '' ? (int)$request->request->get('days') : null;
        $relations->restrict($me, $target, $reason, $days);        $this->addFlash('success', '⏱ Utilisateur restreint.');
        $redirect = $request->request->get('redirect', $request->headers->get('referer', '/forum'));
        return $this->redirect($redirect);
    }

    #[Route('/user/{id}/unrestrict', name: 'app_user_unrestrict', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function unrestrictUser(int $id, EntityManagerInterface $em, UserRelationService $relations): Response
    {
        $me     = $em->find(\App\Entity\User::class, $this->getCurrentUserId());
        $target = $em->find(\App\Entity\User::class, $id);
        if ($me && $target) {
            $relations->unrestrict($me, $target);
            $this->addFlash('success', '✅ Restriction levée.');
        }
        return $this->redirectToRoute('app_forum_blocked_users');
    }

    #[Route('/blocked-users', name: 'app_forum_blocked_users')]
    public function blockedUsers(EntityManagerInterface $em, UserBlockRepository $blockRepo, UserPeerRestrictionRepository $restrictRepo): Response
    {
        $blocks      = $blockRepo->findBy(['blocker' => $this->getCurrentUserId()]);
        $restrictions = $restrictRepo->findBy(['restrictor' => $this->getCurrentUserId(), 'active' => true]);
        return $this->render('forum/blocked_users.html.twig', [
            'blocks'       => $blocks,
            'restrictions' => $restrictions,
        ]);
    }

    // ── Extra pages ───────────────────────────────────────────────────────────

    #[Route('/api/search-forums', name: 'api_search_forums')]
    public function searchForumsApi(Request $request, ForumRepository $forumRepo): Response
    {
        $q = $request->query->get('q', '');
        if (strlen($q) < 1) return $this->json([]);
        $results = $forumRepo->createQueryBuilder('f')
            ->select('f.id, f.title')
            ->where('f.title LIKE :q')->setParameter('q', '%' . $q . '%')
            ->setMaxResults(8)->getQuery()->getResult();
        return $this->json($results);
    }

    #[Route('/api/search-posts', name: 'api_search_posts')]
    public function searchPostsApi(Request $request, PostRepository $postRepo): Response
    {
        $q       = $request->query->get('q', '');
        $forumId = $request->query->get('forum', '');
        if (strlen($q) < 1) return $this->json([]);
        $qb = $postRepo->createQueryBuilder('p')
            ->select('p.id, p.title')
            ->where('p.title LIKE :q')->setParameter('q', '%' . $q . '%');
        if ($forumId) $qb->andWhere('p.forum = :f')->setParameter('f', $forumId);
        $results = $qb->setMaxResults(8)->getQuery()->getResult();
        return $this->json($results);
    }

    #[Route('/ai-assistant', name: 'app_forum_ai_assistant')]
    public function aiAssistant(): Response
    {
        return $this->render('forum/ai_assistant_view.html.twig');
    }

    #[Route('/ai-generator', name: 'app_forum_ai_generator')]
    public function aiGenerator(ForumRepository $forumRepo): Response
    {
        return $this->render('forum/ai_post_generator_view.html.twig', ['forums' => $forumRepo->findAll()]);
    }

    #[Route('/alerts', name: 'app_forum_alerts')]
    public function alerts(Request $request, AlertRepository $alertRepo): Response
    {
        $type   = $request->query->get('type', '');
        $alerts = $alertRepo->findForUser($this->getCurrentUserId(), $type ?: null);
        return $this->render('forum/alerts_view.html.twig', [
            'alerts'      => $alerts,
            'activeType'  => $type,
            'unreadCount' => $alertRepo->countUnread($this->getCurrentUserId()),
        ]);
    }

    #[Route('/alerts/mark-read', name: 'app_alerts_mark_read', methods: ['POST'])]
    public function markAllRead(EntityManagerInterface $em, AlertRepository $alertRepo): Response
    {
        foreach ($alertRepo->findForUser($this->getCurrentUserId()) as $alert) {
            $alert->setIsRead(true);
        }
        $em->flush();
        return $this->redirectToRoute('app_forum_alerts');
    }

    #[Route('/alerts/delete-all', name: 'app_alerts_delete_all', methods: ['POST'])]
    public function deleteAllAlerts(EntityManagerInterface $em, AlertRepository $alertRepo): Response
    {
        foreach ($alertRepo->findForUser($this->getCurrentUserId()) as $alert) {
            $em->remove($alert);
        }
        $em->flush();
        return $this->redirectToRoute('app_forum_alerts');
    }

    #[Route('/alerts/{id}/delete', name: 'app_alert_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteAlert(\App\Entity\Alert $alert, EntityManagerInterface $em): Response
    {
        $em->remove($alert);
        $em->flush();
        return $this->redirectToRoute('app_forum_alerts');
    }

    #[Route('/personality', name: 'app_forum_personality')]
    public function personality(
        \App\Repository\PostRepository $postRepo,
        \App\Repository\CommentRepository $commentRepo,
        \App\Repository\VoteRepository $voteRepo,
        \App\Repository\UserForumRepository $ufRepo,
        \App\Repository\SharedPostRepository $sharedRepo,
        AiService $ai
    ): Response {
        $posts    = $postRepo->findBy(['author' => $this->getCurrentUserId()]);
        $comments = $commentRepo->findBy(['author' => $this->getCurrentUserId()]);
        $votes    = $voteRepo->findBy(['user' => $this->getCurrentUserId()]);
        $forums   = $ufRepo->findBy(['user' => $this->getCurrentUserId()]);
        $shares   = $sharedRepo->findBy(['user' => $this->getCurrentUserId()]);
        $upvotes  = count(array_filter($votes, fn($v) => $v->getVoteType() === 'UPVOTE'));

        $stats = [
            'posts'     => count($posts),
            'comments'  => count($comments),
            'votes'     => count($votes),
            'forums'    => count($forums),
            'shares'    => count($shares),
            'upvotes'   => $upvotes,
            'downvotes' => count($votes) - $upvotes,
        ];

        $personality = $ai->analyzePersonality($stats);
        $stats['postsPerDay'] = $stats['posts'] > 0 ? round($stats['posts'] / max(1, 30), 1) : 0;
        $growthPlan  = $ai->generateGrowthPlan($stats);

        return $this->render('forum/personality_view.html.twig', [
            'personality' => $personality,
            'stats'       => $stats,
            'growthPlan'  => $growthPlan,
        ]);
    }

    // ── AI API endpoints ──────────────────────────────────────────────────────

    #[Route('/api/ai/generate-image', name: 'api_ai_generate_image', methods: ['POST'])]
    public function aiGenerateImage(Request $request, AiService $ai): Response
    {
        $data   = json_decode($request->getContent(), true);
        $prompt = trim($data['prompt'] ?? '');
        if (!$prompt) return $this->json(['error' => 'empty prompt'], 400);

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/images';
        $path      = $ai->generateImage($prompt, $uploadDir);

        if (!$path) return $this->json(['error' => 'generation failed'], 500);

        return $this->json(['image_url' => '/' . $path]);
    }

    #[Route('/api/ai/rewrite', name: 'api_ai_rewrite', methods: ['POST'])]
    public function aiRewrite(Request $request, AiService $ai): Response
    {
        $data = json_decode($request->getContent(), true);
        $text = $data['text'] ?? '';
        if (strlen($text) < 10) return $this->json(['error' => 'text too short'], 400);
        return $this->json(['rewritten' => $ai->rewriteText($text)]);
    }

    #[Route('/api/ai/chat', name: 'api_ai_chat', methods: ['POST'])]
    public function aiChat(Request $request, AiService $ai): Response
    {
        $data    = json_decode($request->getContent(), true);
        $message = $data['message'] ?? '';
        $history = $data['history'] ?? [];
        if (!$message) return $this->json(['error' => 'empty'], 400);
        return $this->json(['reply' => $ai->chat($message, $history)]);
    }

    #[Route('/api/ai/transcribe', name: 'api_ai_transcribe', methods: ['POST'])]
    public function aiTranscribe(Request $request, \App\Service\WhisperService $whisper): Response
    {
        $audioFile = $request->files->get('audio');
        if (!$audioFile) {
            return $this->json(['error' => 'No audio file provided'], 400);
        }

        try {
            $tempPath = $audioFile->getRealPath();
            $language = $request->request->get('language', '');
            
            $text = $language 
                ? $whisper->transcribe($tempPath, $language)
                : $whisper->transcribe($tempPath);
            
            return $this->json(['text' => $text]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/ai/speech-to-speech', name: 'api_ai_speech_to_speech', methods: ['POST'])]
    public function speechToSpeech(Request $request, \App\Service\SpeechToSpeechService $s2s): Response
    {
        $audioFile = $request->files->get('audio');
        if (!$audioFile) {
            return $this->json(['error' => 'No audio file provided'], 400);
        }

        try {
            // Sauvegarder le fichier avec l'extension .webm
            $uploadDir = $this->getParameter('kernel.project_dir') . '/var/tmp';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $filename = 'recording_' . uniqid() . '.webm';
            $tempPath = $uploadDir . '/' . $filename;
            $audioFile->move($uploadDir, $filename);
            
            $language = $request->request->get('language', '');
            $history = json_decode($request->request->get('history', '[]'), true) ?? [];
            
            $result = $s2s->processAudioToAudio($tempPath, $history, $language);
            
            // Nettoyer le fichier temporaire
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
            
            return $this->json([
                'text' => $result['text'],
                'reply' => $result['reply'],
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/ai/generate-post', name: 'api_ai_generate_post', methods: ['POST'])]
    public function aiGeneratePost(Request $request, AiService $ai): Response
    {
        $data  = json_decode($request->getContent(), true);
        $theme = $data['theme'] ?? '';
        $tone  = $data['tone']  ?? 'professional';
        $len   = $data['length'] ?? 'medium';
        if (!$theme) return $this->json(['error' => 'empty'], 400);
        return $this->json($ai->generatePost($theme, $tone, $len));
    }

    #[Route('/api/ai/summarize', name: 'api_ai_summarize', methods: ['POST'])]
    public function aiSummarize(Request $request, AiService $ai): Response
    {
        $data = json_decode($request->getContent(), true);
        return $this->json(['summary' => $ai->summarize($data['title'] ?? '', $data['content'] ?? '')]);
    }

    #[Route('/api/ai/translate', name: 'api_ai_translate', methods: ['POST'])]
    public function aiTranslate(Request $request, AiService $ai): Response
    {
        $data = json_decode($request->getContent(), true);
        return $this->json(['translation' => $ai->translate($data['text'] ?? '', $data['lang'] ?? 'English')]);
    }

    #[Route('/api/ai/moderate', name: 'api_ai_moderate', methods: ['POST'])]
    public function aiModerate(Request $request, AiService $ai): Response
    {
        $data = json_decode($request->getContent(), true);
        return $this->json($ai->moderateComment($data['text'] ?? ''));
    }

    #[Route('/api/ai/autocomplete', name: 'api_ai_autocomplete', methods: ['POST'])]
    public function aiAutocomplete(Request $request, AiService $ai): Response
    {
        $data = json_decode($request->getContent(), true);
        $text = $data['text'] ?? '';
        if (strlen($text) < 10) return $this->json(['suggestion' => '']);
        return $this->json(['suggestion' => $ai->autocomplete($text)]);
    }

    #[Route('/api/ai/misinformation', name: 'api_ai_misinformation', methods: ['POST'])]
    public function aiMisinformation(Request $request, AiService $ai): Response
    {
        $data = json_decode($request->getContent(), true);
        return $this->json($ai->detectMisinformation($data['title'] ?? '', $data['content'] ?? ''));
    }

    #[Route('/api/ai/toxicity', name: 'api_ai_toxicity', methods: ['POST'])]
    public function aiToxicity(Request $request, AiService $ai): Response
    {
        $data     = json_decode($request->getContent(), true);
        $comments = $data['comments'] ?? [];
        return $this->json($ai->detectToxicity($comments));
    }

    #[Route('/recommendations', name: 'app_forum_recommendations')]
    public function recommendations(ForumRecommendationRepository $recRepo): Response
    {
        $recommendations = $recRepo->createQueryBuilder('r')
            ->where('r.user = :uid')->setParameter('uid', $this->getCurrentUserId())
            ->orderBy('r.score', 'DESC')->getQuery()->getResult();
        return $this->render('forum/recommendations_view.html.twig', ['recommendations' => $recommendations]);
    }

    #[Route('/recommendations/refresh', name: 'app_forum_recommendations_refresh', methods: ['POST'])]
    public function refreshRecommendations(RecommendationService $recService): Response
    {
        $recService->refresh($this->getCurrentUserId());
        return $this->redirectToRoute('app_forum_recommendations');
    }

    #[Route('/policy', name: 'forum_policy')]
    public function policy(): Response
    {
        return $this->render('forum/policy.html.twig');
    }
}

