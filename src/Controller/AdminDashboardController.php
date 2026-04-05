<?php
namespace App\Controller;

use App\Dto\RestrictionDto;
use App\Entity\Alert;
use App\Entity\FlaggedContent;
use App\Enum\DeletionReason;
use App\Repository\CommentRepository;
use App\Repository\FlaggedContentRepository;
use App\Repository\PostRepository;
use App\Repository\UserRepository;
use App\Repository\UserRestrictionRepository;
use App\Service\AdminEmailService;
use App\Service\AdminModerationService;
use App\Service\AdminRestrictionService;
use App\Service\AiService;
use App\Service\AlertService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/forum/admin', name: 'admin_')]
class AdminDashboardController extends AbstractController
{
    // ── Content Detail Views ──────────────────────────────────────────────────

    #[Route('/view/forum/{id}', name: 'view_forum')]
    public function viewForum(int $id, \App\Repository\ForumRepository $forumRepo): Response
    {
        $forum = $forumRepo->find($id);
        if (!$forum) throw $this->createNotFoundException('Forum not found');
        return $this->render('forum dashboard/view_forum.html.twig', ['forum' => $forum]);
    }

    #[Route('/view/post/{id}', name: 'view_post')]
    public function viewPost(int $id, \App\Repository\PostRepository $postRepo): Response
    {
        $post = $postRepo->find($id);
        if (!$post) throw $this->createNotFoundException('Post not found');
        return $this->render('forum dashboard/view_post.html.twig', ['post' => $post]);
    }

    #[Route('/view/comment/{id}', name: 'view_comment')]
    public function viewComment(int $id, \App\Repository\CommentRepository $commentRepo): Response
    {
        $comment = $commentRepo->find($id);
        if (!$comment) throw $this->createNotFoundException('Comment not found');
        return $this->render('forum dashboard/view_comment.html.twig', ['comment' => $comment]);
    }

    #[Route('/reports', name: 'reports')]
    public function reports(\App\Repository\UserReportRepository $reportRepo): Response
    {
        return $this->render('forum dashboard/reports.html.twig', [
            'reports' => $reportRepo->findUntreated(),
            'treated' => $reportRepo->findBy(['treated' => true], ['createdAt' => 'DESC'], 20),
        ]);
    }

    #[Route('/reports/{id}/treat', name: 'treat_report', methods: ['POST'])]
    public function treatReport(int $id, \App\Repository\UserReportRepository $reportRepo, EntityManagerInterface $em): Response
    {
        $report = $reportRepo->find($id);
        if ($report) { $report->setTreated(true); $em->flush(); }
        return $this->json(['success' => true]);
    }

    // ── Overview ──────────────────────────────────────────────────────────────

    #[Route('', name: 'overview')]
    public function overview(
        AdminModerationService $moderation,
        FlaggedContentRepository $flaggedRepo
    ): Response {
        $stats     = $moderation->getContentStats();
        $chartData = $moderation->getActivityData(30);
        $recentFlags = $flaggedRepo->findUnreviewed();
        $recentFlags = array_slice($recentFlags, 0, 5);

        return $this->render('forum dashboard/overview.html.twig', [
            'stats'       => $stats,
            'chartData'   => $chartData,
            'recentFlags' => $recentFlags,
        ]);
    }

    // ── Moderation ────────────────────────────────────────────────────────────

    #[Route('/moderation', name: 'moderation')]
    public function moderation(
        Request $request,
        AdminModerationService $moderation
    ): Response {
        $type   = $request->query->get('type', 'post');
        $sort   = $request->query->get('sort', 'recent');
        $search = $request->query->get('q', '');
        $page   = max(1, (int) $request->query->get('page', 1));

        $data    = $moderation->getPaginatedContent($type, $sort, $search, $page);
        $reasons = DeletionReason::cases();

        return $this->render('forum dashboard/moderation.html.twig', [
            'items'   => $data['items'],
            'total'   => $data['total'],
            'pages'   => $data['pages'],
            'page'    => $page,
            'type'    => $type,
            'sort'    => $sort,
            'search'  => $search,
            'reasons' => $reasons,
        ]);
    }

    #[Route('/delete/{type}/{id}', name: 'delete_content', methods: ['POST'])]
    public function deleteContent(
        string $type,
        int $id,
        Request $request,
        AdminModerationService $moderation,
        AdminEmailService $mailer,
        AlertService $alerts,
        EntityManagerInterface $em
    ): Response {
        $reason = $request->request->get('reason', 'Violation des règles de la communauté');

        try {
            $result = $moderation->deleteContent($type, $id);
            $author = $result['author'];
            $title  = $result['title'];

            if ($author) {
                $mailer->sendContentRemovedEmail($author, $reason, $title);
                $alerts->create(
                    $author->getId(),
                    Alert::TYPE_MODERATION,
                    '🚨 Votre contenu "' . $title . '" a été supprimé. Raison : ' . $reason,
                    '/forum/policy'
                );
                $em->flush();
            }

            return $this->json(['success' => true, 'message' => 'Contenu supprimé avec succès.']);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // ── Users ─────────────────────────────────────────────────────────────────

    #[Route('/users/{id}', name: 'user_detail')]
    public function userDetail(
        int $id,
        UserRepository $userRepo,
        UserRestrictionRepository $restrictionRepo
    ): Response {
        $user = $userRepo->find($id);
        if (!$user) throw $this->createNotFoundException('User not found');

        $users = $userRepo->createQueryBuilder('u')->orderBy('u.created_at', 'DESC')->getQuery()->getResult();
        $restrictions = [];
        foreach ($users as $u) {
            $restrictions[$u->getId()] = $restrictionRepo->findActiveForUser($u->getId());
        }

        return $this->render('forum dashboard/users.html.twig', [
            'users'        => $users,
            'restrictions' => $restrictions,
            'search'       => '',
            'autoOpenId'   => $id,
        ]);
    }

    #[Route('/users', name: 'users')]
    public function users(
        Request $request,
        UserRepository $userRepo,
        UserRestrictionRepository $restrictionRepo
    ): Response {
        $search = $request->query->get('q', '');
        $qb = $userRepo->createQueryBuilder('u');
        if ($search) {
            $qb->where('u.firstname LIKE :q OR u.lastname LIKE :q OR u.email LIKE :q')
               ->setParameter('q', "%$search%");
        }
        $users = $qb->orderBy('u.created_at', 'DESC')->getQuery()->getResult();

        $restrictions = [];
        foreach ($users as $user) {
            $restrictions[$user->getId()] = $restrictionRepo->findActiveForUser($user->getId());
        }

        return $this->render('forum dashboard/users.html.twig', [
            'users'        => $users,
            'restrictions' => $restrictions,
            'search'       => $search,
        ]);
    }

    #[Route('/users/{id}/activity', name: 'user_activity')]
    public function userActivity(int $id, AdminModerationService $moderation): Response
    {
        return $this->json($moderation->getUserActivityData($id, 30));
    }

    #[Route('/users/{id}/unban', name: 'unban_user', methods: ['POST'])]
    public function unbanUser(
        int $id,
        UserRepository $userRepo,
        EntityManagerInterface $em
    ): Response {
        $user = $userRepo->find($id);
        if (!$user) return $this->json(['error' => 'User not found'], 404);
        $user->setRole('USER');
        $em->flush();
        return $this->json(['success' => true]);
    }

    #[Route('/users/{id}/unrestrict', name: 'unrestrict_user', methods: ['POST'])]
    public function unrestrictUser(
        int $id,
        UserRepository $userRepo,
        \App\Repository\UserRestrictionRepository $restrictionRepo,
        EntityManagerInterface $em
    ): Response {
        $user = $userRepo->find($id);
        if (!$user) return $this->json(['error' => 'User not found'], 404);
        $restriction = $restrictionRepo->findActiveForUser($user->getId());
        if ($restriction) {
            $restriction->setActive(false);
            $em->flush();
        }
        return $this->json(['success' => true]);
    }

    #[Route('/users/{id}/warn', name: 'warn_user', methods: ['POST'])]
    public function warnUser(
        int $id,
        Request $request,
        UserRepository $userRepo,
        AdminEmailService $mailer,
        AlertService $alerts,
        EntityManagerInterface $em
    ): Response {
        $user = $userRepo->find($id);
        if (!$user) return $this->json(['error' => 'User not found'], 404);
        if ($user->getRole() === 'BANNED') return $this->json(['error' => 'User is already banned'], 409);

        $reason = $request->request->get('reason', 'Comportement contraire aux règles');
        $mailer->sendWarningEmail($user, $reason);
        $alerts->create($user->getId(), Alert::TYPE_WARNING, '⚠️ Avertissement : ' . $reason, '/forum/policy');
        $em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/users/{id}/restrict', name: 'restrict_user', methods: ['POST'])]
    public function restrictUser(
        int $id,
        Request $request,
        UserRepository $userRepo,
        AdminRestrictionService $restriction,
        AdminEmailService $mailer,
        AlertService $alerts,
        EntityManagerInterface $em
    ): Response {
        $user = $userRepo->find($id);
        if (!$user) return $this->json(['error' => 'User not found'], 404);
        if ($user->getRole() === 'BANNED') return $this->json(['error' => 'User is already banned'], 409);

        $dto = new RestrictionDto();
        $dto->days           = max(0, (int) $request->request->get('days', 7));
        $dto->canPost        = (bool) $request->request->get('canPost', false);
        $dto->canComment     = (bool) $request->request->get('canComment', false);
        $dto->canCreateForum = (bool) $request->request->get('canCreateForum', false);
        $dto->reason         = $request->request->get('reason', 'Infraction aux règles');
        $dto->offenseNumber  = (int) $request->request->get('offense', 2);

        $restriction->applyRestriction($user, $dto);
        $mailer->sendRestrictionEmail($user, $dto);
        $alerts->create($user->getId(), Alert::TYPE_RESTRICTION, '⏱ Votre compte a été restreint pour ' . $dto->days . ' jours. Raison : ' . $dto->reason, '/forum/policy');
        $em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/users/{id}/ban', name: 'ban_user', methods: ['POST'])]
    public function banUser(
        int $id,
        Request $request,
        UserRepository $userRepo,
        AdminRestrictionService $restriction,
        AdminEmailService $mailer,
        AlertService $alerts,
        EntityManagerInterface $em
    ): Response {
        $user = $userRepo->find($id);
        if (!$user) return $this->json(['error' => 'User not found'], 404);
        if ($user->getRole() === 'BANNED') return $this->json(['error' => 'User is already banned'], 409);

        $reason = $request->request->get('reason', 'Infractions répétées');
        $restriction->banUser($user);
        $mailer->sendBanEmail($user, $reason);
        $alerts->create($user->getId(), Alert::TYPE_BAN, '🚫 Votre compte a été définitivement banni. Raison : ' . $reason, '/forum/policy');
        $em->flush();

        return $this->json(['success' => true]);
    }

    // ── AI Flags ──────────────────────────────────────────────────────────────

    #[Route('/ai-flags', name: 'ai_flags')]
    public function aiFlags(
        Request $request,
        FlaggedContentRepository $flaggedRepo
    ): Response {
        $filter = $request->query->get('filter', 'all');

        $items = match($filter) {
            'misinformation' => $flaggedRepo->findByType('misinformation'),
            'toxic'          => $flaggedRepo->findByType('toxic'),
            'unreviewed'     => $flaggedRepo->findUnreviewed(),
            default          => $flaggedRepo->findBy([], ['detectedAt' => 'DESC']),
        };

        return $this->render('forum dashboard/ai_flags.html.twig', [
            'items'  => $items,
            'filter' => $filter,
        ]);
    }

    #[Route('/ai/scan', name: 'ai_scan', methods: ['POST'])]
    public function runAiScan(
        AiService $ai,
        PostRepository $postRepo,
        CommentRepository $commentRepo,
        FlaggedContentRepository $flaggedRepo,
        EntityManagerInterface $em
    ): Response {
        $scanned = 0;
        $flagged = 0;

        $posts = $postRepo->createQueryBuilder('p')->orderBy('p.createdAt', 'DESC')->setMaxResults(25)->getQuery()->getResult();
        foreach ($posts as $post) {
            if ($flaggedRepo->existsForContent('post', $post->getId())) continue;
            try {
                $result = $ai->detectMisinformation($post->getTitle(), $post->getContent());
                $scanned++;
                if ($result['flagged'] ?? false) {
                    $issues = $result['issues'] ?? [];
                    $fc = new FlaggedContent();
                    $fc->setContentType('post')->setContentId($post->getId())
                       ->setSeverity(count($issues) >= 3 ? 'high' : (count($issues) >= 2 ? 'medium' : 'low'))
                       ->setFlagType('misinformation')->setVerdict($result['verdict'] ?? '')->setIssues($issues);
                    $em->persist($fc);
                    $flagged++;
                }
            } catch (\Throwable) {}
        }

        $comments = $commentRepo->createQueryBuilder('c')->orderBy('c.createdAt', 'DESC')->setMaxResults(25)->getQuery()->getResult();
        foreach ($comments as $comment) {
            if ($flaggedRepo->existsForContent('comment', $comment->getId())) continue;
            try {
                $result = $ai->moderateComment($comment->getContent());
                $scanned++;
                if (!($result['safe'] ?? true)) {
                    $fc = new FlaggedContent();
                    $fc->setContentType('comment')->setContentId($comment->getId())
                       ->setSeverity('medium')->setFlagType('toxic')
                       ->setVerdict($result['warning'] ?? '')->setIssues([$result['warning'] ?? '']);
                    $em->persist($fc);
                    $flagged++;
                }
            } catch (\Throwable) {}
        }

        $em->flush();
        return $this->json(['scanned' => $scanned, 'flagged' => $flagged]);
    }

    #[Route('/ai-flags/{id}/view', name: 'view_flag')]
    public function viewFlag(
        int $id,
        FlaggedContentRepository $flaggedRepo,
        \App\Repository\PostRepository $postRepo,
        \App\Repository\CommentRepository $commentRepo,
        \App\Repository\UserRepository $userRepo,
        \App\Repository\UserRestrictionRepository $restrictionRepo
    ): Response {
        $flag = $flaggedRepo->find($id);
        if (!$flag) throw $this->createNotFoundException('Flag not found');

        $content = null;
        $author  = null;

        if ($flag->getContentType() === 'post') {
            $content = $postRepo->find($flag->getContentId());
            $author  = $content?->getAuthor();
        } else {
            $content = $commentRepo->find($flag->getContentId());
            $author  = $content?->getAuthor();
        }

        $restriction = $author ? $restrictionRepo->findActiveForUser($author->getId()) : null;

        return $this->render('forum dashboard/view_flag.html.twig', [
            'flag'        => $flag,
            'content'     => $content,
            'author'      => $author,
            'restriction' => $restriction,
        ]);
    }

    #[Route('/ai-flags/{id}/ignore', name: 'ignore_flag', methods: ['POST'])]
    public function ignoreFlag(int $id, FlaggedContentRepository $flaggedRepo, EntityManagerInterface $em): Response
    {
        $flag = $flaggedRepo->find($id);
        if (!$flag) return $this->json(['error' => 'Not found'], 404);
        $flag->setIgnored(true)->setReviewed(true);
        $em->flush();
        return $this->json(['success' => true]);
    }

    // ── Policy ────────────────────────────────────────────────────────────────

    // Two routes: the public /forum/policy (absolute) and /forum/admin/policy (via prefix)
    #[Route('/policy', name: 'admin_policy')]
    public function policy(): Response
    {
        return $this->render('forum dashboard/policy.html.twig');
    }

    #[Route('/test-email', name: 'test_email')]
    public function testEmail(AdminEmailService $mailer, UserRepository $userRepo): Response
    {
        $user = $userRepo->find(18);
        if (!$user) return $this->json(['error' => 'User 18 not found'], 404);

        $mailer->sendWarningEmail($user, 'Contenu toxique ou offensant');

        return $this->json(['success' => true, 'sent_to' => $user->getEmail()]);
    }

    // ── Badge Management ──────────────────────────────────────────────────────

    #[Route('/badges', name: 'badges')]
    public function badges(\App\Repository\BadgeTypeRepository $badgeRepo): Response
    {
        return $this->render('forum dashboard/badges.html.twig', [
            'badges' => $badgeRepo->findAll(),
        ]);
    }

    #[Route('/badges/create', name: 'badge_create', methods: ['POST'])]
    public function createBadge(Request $request, EntityManagerInterface $em): Response
    {
        $badge = new \App\Entity\BadgeType();
        $badge->setName($request->request->get('name'));
        $badge->setDescription($request->request->get('description'));
        $badge->setIcon($request->request->get('icon', '🏆'));
        $badge->setCategory($request->request->get('category'));
        $badge->setRequirementType($request->request->get('requirementType', 'posts'));
        $badge->setRequirementValue((int) $request->request->get('requirementValue', 1));
        $em->persist($badge);
        $em->flush();
        return $this->redirectToRoute('admin_badges');
    }

    #[Route('/badges/{id}/delete', name: 'badge_delete', methods: ['POST'])]
    public function deleteBadge(int $id, \App\Repository\BadgeTypeRepository $badgeRepo, EntityManagerInterface $em): Response
    {
        $badge = $badgeRepo->find($id);
        if ($badge) { $em->remove($badge); $em->flush(); }
        return $this->redirectToRoute('admin_badges');
    }
}
