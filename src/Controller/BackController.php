<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Transaction;
use App\Entity\Forums;
use App\Entity\Posts;
use App\Entity\Comments;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
final class BackController extends AbstractController
{
    #[Route('', name: 'backoffice_dashboard', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $userRepo = $em->getRepository(User::class);
        $transactionRepo = $em->getRepository(Transaction::class);
        $forumRepo = $em->getRepository(Forums::class);
        $postRepo = $em->getRepository(Posts::class);
        $commentRepo = $em->getRepository(Comments::class);
        
        $totalUsers = $userRepo->count(['role' => 'USER']);
        $totalAdmins = $userRepo->count(['role' => 'ADMIN']);
        
        // Stats Financières
        $totalSolde = $em->createQueryBuilder()
            ->select('SUM(u1.solde)')
            ->from(User::class, 'u1')
            ->getQuery()
            ->getSingleScalarResult() ?: 0;
            
        $totalTransactions = $transactionRepo->count([]);
        $sumTransactions = $em->createQueryBuilder()
            ->select('SUM(t.amount)')
            ->from(Transaction::class, 't')
            ->getQuery()
            ->getSingleScalarResult() ?: 0;

        // Stats Communauté
        $totalForums = $forumRepo->count([]);
        $totalPosts = $postRepo->count([]);
        $totalComments = $commentRepo->count([]);

        // Derniers utilisateurs inscrits
        $lastUsers = $userRepo->findBy([], ['created_at' => 'DESC'], 5);

        // Répartition des rôles
        $rolesDistribution = $em->createQueryBuilder()
            ->select('u3.role as roleName, COUNT(u3.id) as count')
            ->from(User::class, 'u3')
            ->groupBy('u3.role')
            ->getQuery()
            ->getResult();

        // Top 5 Forums par activité (simulation basée sur l'existence, idéalement jointure sur posts)
        $topForums = $forumRepo->findBy([], ['created_at' => 'DESC'], 5);

        return $this->render('backoffice/index.html.twig', [
            'stats' => [
                'total_users' => $totalUsers,
                'total_admins' => $totalAdmins,
                'total_solde' => number_format((float)$totalSolde, 2, ',', ' '),
                'total_transactions' => $totalTransactions,
                'sum_transactions' => number_format((float)$sumTransactions, 2, ',', ' '),
                'total_forums' => $totalForums,
                'total_posts' => $totalPosts,
                'total_comments' => $totalComments,
                'roles_distribution' => $rolesDistribution,
                'last_users' => $lastUsers,
                'top_forums' => $topForums
            ]
        ]);
    }

    #[Route('/signin', name: 'backoffice_signin', methods: ['GET'])]
    public function signin(): Response
    {
        return $this->render('backoffice/signin.html.twig');
    }

    #[Route('/signup', name: 'backoffice_signup', methods: ['GET'])]
    public function signup(): Response
    {
        return $this->render('backoffice/signup.html.twig');
    }

    #[Route('/inventory', name: 'backoffice_inventory', methods: ['GET'])]
    public function inventory(): Response
    {
        return $this->render('backoffice/inventory.html.twig');
    }

    #[Route('/create-product', name: 'backoffice_create_product', methods: ['GET'])]
    public function createProduct(): Response
    {
        return $this->render('backoffice/create-product.html.twig');
    }

    #[Route('/reports', name: 'backoffice_reports', methods: ['GET'])]
    public function reports(): Response
    {
        return $this->render('backoffice/reports.html.twig');
    }

    #[Route('/docs', name: 'backoffice_docs', methods: ['GET'])]
    public function docs(): Response
    {
        return $this->render('backoffice/docs.html.twig');
    }

    #[Route('/404', name: 'backoffice_404', methods: ['GET'])]
    public function error404(): Response
    {
        return $this->render('backoffice/404-error.html.twig');
    }

    #[Route('/users', name: 'backoffice_users', methods: ['GET'])]
    public function users(EntityManagerInterface $em): Response
    {
        $users = $em->getRepository(User::class)->findAll();

        return $this->render('backoffice/users.html.twig', [
            'users' => $users
        ]);
    }
}
