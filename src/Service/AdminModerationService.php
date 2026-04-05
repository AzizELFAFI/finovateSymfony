<?php
namespace App\Service;

use App\Repository\ForumRepository;
use App\Repository\PostRepository;
use App\Repository\CommentRepository;
use App\Repository\UserRepository;
use App\Repository\FlaggedContentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminModerationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ForumRepository $forumRepo,
        private PostRepository $postRepo,
        private CommentRepository $commentRepo,
        private UserRepository $userRepo,
        private FlaggedContentRepository $flaggedRepo,
    ) {}

    public function deleteContent(string $type, int $id): array
    {
        // Returns ['author' => User|null, 'title' => string]
        return match($type) {
            'forum'   => $this->deleteEntity($this->forumRepo->find($id), fn($e) => $e->getCreator(), fn($e) => $e->getTitle()),
            'post'    => $this->deleteEntity($this->postRepo->find($id), fn($e) => $e->getAuthor(), fn($e) => $e->getTitle()),
            'comment' => $this->deleteEntity($this->commentRepo->find($id), fn($e) => $e->getAuthor(), fn($e) => substr($e->getContent(), 0, 80)),
            default   => throw new \InvalidArgumentException("Invalid type: $type"),
        };
    }

    private function deleteEntity(?object $entity, callable $getAuthor, callable $getTitle): array
    {
        if (!$entity) throw new NotFoundHttpException('Content not found');
        $author = $getAuthor($entity);
        $title  = $getTitle($entity);
        $this->em->remove($entity);
        $this->em->flush();
        return ['author' => $author, 'title' => $title];
    }

    public function getContentStats(): array
    {
        $today = new \DateTimeImmutable('today midnight');
        return [
            'forums'        => (int) $this->forumRepo->createQueryBuilder('f')->select('COUNT(f.id)')->getQuery()->getSingleScalarResult(),
            'posts'         => (int) $this->postRepo->createQueryBuilder('p')->select('COUNT(p.id)')->getQuery()->getSingleScalarResult(),
            'comments'      => (int) $this->commentRepo->createQueryBuilder('c')->select('COUNT(c.id)')->getQuery()->getSingleScalarResult(),
            'users'         => (int) $this->userRepo->createQueryBuilder('u')->select('COUNT(u.id)')->getQuery()->getSingleScalarResult(),
            'flagged'       => count($this->flaggedRepo->findUnreviewed()),
            'forumsToday'   => (int) $this->forumRepo->createQueryBuilder('f')->select('COUNT(f.id)')->where('f.createdAt >= :t')->setParameter('t', $today)->getQuery()->getSingleScalarResult(),
            'postsToday'    => (int) $this->postRepo->createQueryBuilder('p')->select('COUNT(p.id)')->where('p.createdAt >= :t')->setParameter('t', $today)->getQuery()->getSingleScalarResult(),
            'commentsToday' => (int) $this->commentRepo->createQueryBuilder('c')->select('COUNT(c.id)')->where('c.createdAt >= :t')->setParameter('t', $today)->getQuery()->getSingleScalarResult(),
            'usersToday'    => (int) $this->userRepo->createQueryBuilder('u')->select('COUNT(u.id)')->where('u.created_at >= :t')->setParameter('t', $today)->getQuery()->getSingleScalarResult(),
        ];
    }

    public function getActivityData(int $days = 30): array
    {
        $labels = $posts = $comments = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $start = new \DateTimeImmutable("-$i days midnight");
            $end   = $start->modify('+1 day');
            $labels[]   = $start->format('d/m');
            $posts[]    = (int) $this->postRepo->createQueryBuilder('p')->select('COUNT(p.id)')->where('p.createdAt >= :s AND p.createdAt < :e')->setParameter('s', $start)->setParameter('e', $end)->getQuery()->getSingleScalarResult();
            $comments[] = (int) $this->commentRepo->createQueryBuilder('c')->select('COUNT(c.id)')->where('c.createdAt >= :s AND c.createdAt < :e')->setParameter('s', $start)->setParameter('e', $end)->getQuery()->getSingleScalarResult();
        }
        return ['labels' => $labels, 'posts' => $posts, 'comments' => $comments];
    }

    public function getPaginatedContent(string $type, string $sort, string $search, int $page = 1, int $limit = 25): array
    {
        $qb = match($type) {
            'forum'   => $this->forumRepo->createQueryBuilder('e')->leftJoin('e.creator', 'u')->addSelect('u')->leftJoin('e.posts', 'p')->addSelect('p'),
            'post'    => $this->postRepo->createQueryBuilder('e')->leftJoin('e.author', 'u')->addSelect('u')->leftJoin('e.comments', 'c')->addSelect('c'),
            'comment' => $this->commentRepo->createQueryBuilder('e')->leftJoin('e.author', 'u')->addSelect('u'),
            default   => throw new \InvalidArgumentException("Invalid type: $type"),
        };

        if ($search) {
            if ($type === 'comment') {
                $qb->where('e.content LIKE :q OR u.firstname LIKE :q OR u.lastname LIKE :q')->setParameter('q', "%$search%");
            } else {
                $qb->where('e.title LIKE :q OR u.firstname LIKE :q OR u.lastname LIKE :q')->setParameter('q', "%$search%");
            }
        }

        match($sort) {
            'alpha'  => $qb->orderBy($type === 'comment' ? 'e.content' : 'e.title', 'ASC'),
            'author' => $qb->orderBy('u.firstname', 'ASC'),
            'oldest' => $qb->orderBy('e.createdAt', 'ASC'),
            default  => $qb->orderBy('e.createdAt', 'DESC'),
        };

        $total   = count($qb->getQuery()->getResult());
        $results = $qb->setFirstResult(($page - 1) * $limit)->setMaxResults($limit)->getQuery()->getResult();

        return ['items' => $results, 'total' => $total, 'pages' => (int) ceil($total / $limit), 'page' => $page];
    }

    public function getUserActivityData(int $userId, int $days = 30): array
    {
        $labels = $posts = $comments = $votes = $shares = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $start = new \DateTimeImmutable("-$i days midnight");
            $end   = $start->modify('+1 day');
            $labels[]   = $start->format('d/m');
            $posts[]    = (int) $this->postRepo->createQueryBuilder('p')->select('COUNT(p.id)')->where('p.author = :uid AND p.createdAt >= :s AND p.createdAt < :e')->setParameter('uid', $userId)->setParameter('s', $start)->setParameter('e', $end)->getQuery()->getSingleScalarResult();
            $comments[] = (int) $this->commentRepo->createQueryBuilder('c')->select('COUNT(c.id)')->where('c.author = :uid AND c.createdAt >= :s AND c.createdAt < :e')->setParameter('uid', $userId)->setParameter('s', $start)->setParameter('e', $end)->getQuery()->getSingleScalarResult();
            $votes[]    = 0;
            $shares[]   = 0;
        }
        return ['labels' => $labels, 'posts' => $posts, 'comments' => $comments, 'votes' => $votes, 'shares' => $shares];
    }
}
