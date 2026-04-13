<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Transaction;
use App\Entity\Goal;
use App\Entity\Bill;
use App\Entity\Project;
use App\Entity\Investissement;
use App\Entity\Forum;
use App\Entity\Post;
use App\Entity\Comment;
use App\Form\CreateProjectRequestType;
use App\Model\CreateProjectRequest;
use App\Entity\Product;
use App\Entity\Ad;
use App\Entity\UserAdClick;
use App\Form\ProductType;
use App\Form\AdType;
use App\Form\UserAdClickType;
use App\Service\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
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
        $forumRepo = $em->getRepository(Forum::class);
        $postRepo = $em->getRepository(Post::class);
        $commentRepo = $em->getRepository(Comment::class);
        
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
        $topForums = $forumRepo->findBy([], ['createdAt' => 'DESC'], 5);

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
    public function createProductt(): Response
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
    public function users(EntityManagerInterface $em, Request $request): Response
    {
        $search = $request->query->get('search');
        $role = $request->query->get('role');
        $sort = (string) $request->query->get('sort', 'lastname');
        $dir = strtoupper((string) $request->query->get('dir', 'ASC'));

        $allowedSorts = ['lastname', 'firstname', 'email'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'lastname';
        }
        if (!in_array($dir, ['ASC', 'DESC'], true)) {
            $dir = 'ASC';
        }

        $allowedRoles = ['ADMIN', 'USER'];
        if ($role !== null && $role !== '' && !in_array($role, $allowedRoles, true)) {
            $role = null;
        }

        $queryBuilder = $em->getRepository(User::class)->createQueryBuilder('u');

        if ($role) {
            $queryBuilder->andWhere('u.role = :role')
                ->setParameter('role', $role);
        }

        if ($search) {
            $queryBuilder->where('u.email LIKE :search')
                ->orWhere('u.firstname LIKE :search')
                ->orWhere('u.lastname LIKE :search')
                ->orWhere('u.role LIKE :search')
                ->orWhere('u.cin LIKE :search')
                ->orWhere('u.phone_number LIKE :search')
                ->orWhere('u.numero_carte LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $queryBuilder->orderBy('u.' . $sort, $dir)
            ->addOrderBy('u.id', 'DESC');

        $users = $queryBuilder->getQuery()->getResult();

        return $this->render('backoffice/users.html.twig', [
            'users' => $users,
            'search' => $search,
            'role' => $role,
            'sort' => $sort,
            'dir' => $dir
        ]);
    }

    #[Route('/users/report.csv', name: 'backoffice_users_report_csv', methods: ['GET'])]
    public function usersReportCsv(EntityManagerInterface $em, Request $request): Response
    {
        $search = (string) $request->query->get('search', '');
        $role = (string) $request->query->get('role', '');
        $sort = (string) $request->query->get('sort', 'lastname');
        $dir = strtoupper((string) $request->query->get('dir', 'ASC'));

        $allowedSorts = ['lastname', 'firstname', 'email'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'lastname';
        }
        if (!in_array($dir, ['ASC', 'DESC'], true)) {
            $dir = 'ASC';
        }

        $allowedRoles = ['ADMIN', 'USER'];
        if ($role !== '' && !in_array($role, $allowedRoles, true)) {
            $role = '';
        }

        $queryBuilder = $em->getRepository(User::class)->createQueryBuilder('u');

        if ($role !== '') {
            $queryBuilder->andWhere('u.role = :role')
                ->setParameter('role', $role);
        }

        if ($search !== '') {
            $queryBuilder->where('u.email LIKE :search')
                ->orWhere('u.firstname LIKE :search')
                ->orWhere('u.lastname LIKE :search')
                ->orWhere('u.role LIKE :search')
                ->orWhere('u.cin LIKE :search')
                ->orWhere('u.phone_number LIKE :search')
                ->orWhere('u.numero_carte LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $queryBuilder->orderBy('u.' . $sort, $dir)
            ->addOrderBy('u.id', 'DESC');

        $users = $queryBuilder->getQuery()->getResult();

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return new Response('Impossible de générer le fichier CSV.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $asExcelText = static function (string $value): string {
            $v = trim($value);
            return $v === '' ? '' : "\t" . $v;
        };

        fputcsv($handle, ['ID', 'Email', 'Nom', 'Prénom', 'Rôle', 'Solde', 'Points', 'Date naissance', 'CIN', 'Téléphone', 'N° Carte', 'Créé le'], ';');
        foreach ($users as $user) {
            if (!$user instanceof User) {
                continue;
            }

            $createdAt = $user->getCreated_at();
            $createdAtStr = $createdAt instanceof \DateTimeInterface ? $createdAt->format('d/m/Y H:i') : '';

            $birthdate = $user->getBirthdate();
            $birthdateStr = $birthdate instanceof \DateTimeInterface ? $birthdate->format('d/m/Y') : '';

            fputcsv($handle, [
                $asExcelText((string) $user->getId()),
                (string) $user->getEmail(),
                (string) $user->getLastname(),
                (string) $user->getFirstname(),
                (string) $user->getRole(),
                (string) $user->getSolde(),
                (string) $user->getPoints(),
                $asExcelText($birthdateStr),
                $asExcelText((string) $user->getCin()),
                $asExcelText((string) $user->getPhone_number()),
                $asExcelText((string) $user->getNumero_carte()),
                $asExcelText($createdAtStr),
            ], ';');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $csvUtf8 = "sep=;\n" . ($csv ?: '');
        $csvUtf16le = iconv('UTF-8', 'UTF-16LE//IGNORE', $csvUtf8);
        if ($csvUtf16le === false) {
            $csvUtf16le = $csvUtf8;
        } else {
            $csvUtf16le = "\xFF\xFE" . $csvUtf16le;
        }

        $filename = 'users_report_' . (new \DateTime())->format('Y-m-d_H-i') . '.csv';
        $disposition = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename);

        return new Response($csvUtf16le, Response::HTTP_OK, [
            'Content-Type' => ($csvUtf16le === $csvUtf8 ? 'text/csv; charset=UTF-8' : 'text/csv; charset=UTF-16LE'),
            'Content-Disposition' => $disposition,
        ]);
    }

    #[Route('/users/report.pdf', name: 'backoffice_users_report_pdf', methods: ['GET'])]
    public function usersReportPdf(EntityManagerInterface $em, Request $request): Response
    {
        if (!class_exists('Dompdf\\Dompdf')) {
            $this->addFlash('error', 'Export PDF indisponible: installez dompdf/dompdf.');
            return $this->redirectToRoute('backoffice_users', $request->query->all());
        }

        $search = (string) $request->query->get('search', '');
        $role = (string) $request->query->get('role', '');
        $sort = (string) $request->query->get('sort', 'lastname');
        $dir = strtoupper((string) $request->query->get('dir', 'ASC'));

        $allowedSorts = ['lastname', 'firstname', 'email'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'lastname';
        }
        if (!in_array($dir, ['ASC', 'DESC'], true)) {
            $dir = 'ASC';
        }

        $allowedRoles = ['ADMIN', 'USER'];
        if ($role !== '' && !in_array($role, $allowedRoles, true)) {
            $role = '';
        }

        $queryBuilder = $em->getRepository(User::class)->createQueryBuilder('u');

        if ($role !== '') {
            $queryBuilder->andWhere('u.role = :role')
                ->setParameter('role', $role);
        }

        if ($search !== '') {
            $queryBuilder->where('u.email LIKE :search')
                ->orWhere('u.firstname LIKE :search')
                ->orWhere('u.lastname LIKE :search')
                ->orWhere('u.role LIKE :search')
                ->orWhere('u.cin LIKE :search')
                ->orWhere('u.phone_number LIKE :search')
                ->orWhere('u.numero_carte LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $queryBuilder->orderBy('u.' . $sort, $dir)
            ->addOrderBy('u.id', 'DESC');

        $users = $queryBuilder->getQuery()->getResult();

        $html = $this->renderView('backoffice/users_report_pdf.html.twig', [
            'users' => $users,
            'generatedAt' => new \DateTime(),
            'search' => $search,
            'role' => $role,
            'sort' => $sort,
            'dir' => $dir,
        ]);

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'users_report_' . (new \DateTime())->format('Y-m-d_H-i') . '.pdf';
        $disposition = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename);

        return new Response($dompdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition,
        ]);
    }

    #[Route('/users/{id}/delete', name: 'backoffice_user_delete', methods: ['POST'])]
    public function deleteUser(string $id, EntityManagerInterface $em, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete_user_' . $id, (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('backoffice_users');
        }

        $user = $em->getRepository(User::class)->find($id);
        if (!$user instanceof User) {
            return $this->redirectToRoute('backoffice_users');
        }

        $numericId = (int) $user->getId();

        $em->createQueryBuilder()
            ->delete(Transaction::class, 't')
            ->where('t.sender_id = :uid OR t.receiver_id = :uid')
            ->setParameter('uid', $numericId)
            ->getQuery()
            ->execute();

        $em->remove($user);
        $em->flush();

        return $this->redirectToRoute('backoffice_users');
    }

    #[Route('/users/{id}/activity', name: 'backoffice_user_activity', methods: ['GET'])]
    public function userActivity(string $id, EntityManagerInterface $em): Response
    {
        $user = $em->getRepository(User::class)->find($id);
        if (!$user instanceof User) {
            return $this->redirectToRoute('backoffice_users');
        }

        $numericId = (int) $user->getId();

        $transactions = $em->getRepository(Transaction::class)
            ->createQueryBuilder('t')
            ->where('t.sender_id = :uid OR t.receiver_id = :uid')
            ->setParameter('uid', $numericId)
            ->orderBy('t.date', 'DESC')
            ->getQuery()
            ->getResult();

        $relatedUserIds = [];
        foreach ($transactions as $transaction) {
            if ($transaction instanceof Transaction) {
                $relatedUserIds[] = (string) $transaction->getSender_id();
                $relatedUserIds[] = (string) $transaction->getReceiver_id();
            }
        }
        $relatedUserIds = array_values(array_unique($relatedUserIds));

        $usersById = [];
        if ($relatedUserIds !== []) {
            $relatedUsers = $em->getRepository(User::class)
                ->createQueryBuilder('u')
                ->where('u.id IN (:ids)')
                ->setParameter('ids', $relatedUserIds)
                ->getQuery()
                ->getResult();

            foreach ($relatedUsers as $relatedUser) {
                if ($relatedUser instanceof User) {
                    $usersById[(string) $relatedUser->getId()] = $relatedUser;
                }
            }
        }

        $goals = $em->getRepository(Goal::class)->findBy(
            ['id_user' => $numericId],
            ['created_at' => 'DESC']
        );

        $bills = $em->getRepository(Bill::class)->findBy(
            ['id_user' => $numericId],
            ['date_paiement' => 'DESC']
        );

        $projects = $em->getRepository(Project::class)->findBy(
            ['owner' => $user],
            ['created_at' => 'DESC']
        );

        $investissements = $em->getRepository(Investissement::class)->findByInvestorOrdered($user);

        return $this->render('backoffice/user-activity.html.twig', [
            'user' => $user,
            'transactions' => $transactions,
            'usersById' => $usersById,
            'goals' => $goals,
            'bills' => $bills,
            'projects' => $projects,
            'investissements' => $investissements,
        ]);
    }

    #[Route('/projects', name: 'backoffice_projects', methods: ['GET'])]
    public function projects(EntityManagerInterface $em): Response
    {
        $projects = $em->getRepository(Project::class)->findBy([], ['created_at' => 'DESC']);

        return $this->render('backoffice/projects.html.twig', [
            'projects' => $projects,
        ]);
    }

    #[Route('/projects/{id}', name: 'backoffice_project_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function showProject(int $id, EntityManagerInterface $em): Response
    {
        $project = $em->getRepository(Project::class)->find($id);
        if (!$project instanceof Project) {
            return $this->redirectToRoute('backoffice_projects');
        }

        $allInvestissements = $em->getRepository(Investissement::class)->findBy(
            ['project' => $project],
            ['investment_date' => 'DESC']
        );

        $pending = [];
        $confirmed = [];
        $rejected = [];
        foreach ($allInvestissements as $inv) {
            if (!$inv instanceof Investissement) {
                continue;
            }
            if ($inv->getStatus() === 'PENDING') {
                $pending[] = $inv;
            } elseif ($inv->getStatus() === 'CONFIRMED') {
                $confirmed[] = $inv;
            } else {
                $rejected[] = $inv;
            }
        }

        return $this->render('backoffice/project-show.html.twig', [
            'project' => $project,
            'pending' => $pending,
            'confirmed' => $confirmed,
            'rejected' => $rejected,
            'investissements' => $allInvestissements,
        ]);
    }

    #[Route('/projects/new', name: 'backoffice_project_new', methods: ['GET', 'POST'])]
    public function createProject(Request $request, EntityManagerInterface $em): Response
    {
        $data = new CreateProjectRequest();
        $form = $this->createForm(CreateProjectRequestType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $goal = (float) str_replace(',', '.', (string) $data->getGoalAmount());

            $project = new Project();
            $project->setTitle((string) $data->getTitle());
            $project->setDescription((string) $data->getDescription());
            $project->setGoalAmount(number_format($goal, 2, '.', ''));
            $project->setCurrentAmount('0');
            $project->setCreatedAt(new \DateTime());
            $project->setStatus('OPEN');

            $deadline = $data->getDeadline();
            if ($deadline instanceof \DateTimeInterface) {
                $project->setDeadline(\DateTime::createFromInterface($deadline));
            }

            $category = trim((string) ($data->getCategory() ?? ''));
            $project->setCategory($category !== '' ? $category : null);

            $owner = $this->getUser();
            if ($owner instanceof User) {
                $project->setOwner($owner);
            }

            $em->persist($project);
            $em->flush();

            $uploaded = $data->getImage();
            if ($uploaded instanceof UploadedFile) {
                $project->setImagePath($this->storeProjectUploadedImage($uploaded, (int) $project->getId()));
                $em->flush();
            }

            $this->addFlash('success', 'Projet créé avec succès.');

            return $this->redirectToRoute('backoffice_projects');
        }

        return $this->render('backoffice/project-new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/projects/{projectId}/investments/{id}/accept', name: 'backoffice_project_investment_accept', requirements: ['projectId' => '\\d+', 'id' => '\\d+'], methods: ['POST'])]
    public function acceptProjectInvestment(int $projectId, int $id, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('backoffice_project_investment_accept_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Échec de validation du formulaire.');
            return $this->redirectToRoute('backoffice_project_show', ['id' => $projectId]);
        }

        $inv = $em->getRepository(Investissement::class)->find($id);
        if (!$inv instanceof Investissement || $inv->getStatus() !== 'PENDING') {
            $this->addFlash('danger', 'Demande introuvable ou déjà traitée.');
            return $this->redirectToRoute('backoffice_project_show', ['id' => $projectId]);
        }

        $project = $inv->getProject();
        if (!$project instanceof Project || $project->getId() !== $projectId) {
            $this->addFlash('danger', 'Projet introuvable.');
            return $this->redirectToRoute('backoffice_projects');
        }

        $investor = $inv->getUser();
        if (!$investor instanceof User) {
            $this->addFlash('danger', 'Investisseur introuvable.');
            return $this->redirectToRoute('backoffice_project_show', ['id' => $projectId]);
        }

        $amount = (float) str_replace(',', '.', (string) $inv->getAmount());
        $balance = (float) str_replace(',', '.', (string) $investor->getSolde());
        if ($balance < $amount) {
            $this->addFlash('danger', 'Le solde de l\'investisseur est insuffisant pour confirmer ce montant.');
            return $this->redirectToRoute('backoffice_project_show', ['id' => $projectId]);
        }

        $investor->setSolde((string) ($balance - $amount));
        $current = (float) str_replace(',', '.', (string) ($project->getCurrentAmount() ?? '0'));
        $project->setCurrentAmount((string) ($current + $amount));
        $inv->setStatus('CONFIRMED');

        $em->flush();
        $this->addFlash('success', 'Demande acceptée. Le montant a été débité et ajouté au projet.');

        return $this->redirectToRoute('backoffice_project_show', ['id' => $projectId]);
    }

    #[Route('/projects/{projectId}/investments/{id}/reject', name: 'backoffice_project_investment_reject', requirements: ['projectId' => '\\d+', 'id' => '\\d+'], methods: ['POST'])]
    public function rejectProjectInvestment(int $projectId, int $id, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('backoffice_project_investment_reject_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Échec de validation du formulaire.');
            return $this->redirectToRoute('backoffice_project_show', ['id' => $projectId]);
        }

        $inv = $em->getRepository(Investissement::class)->find($id);
        if (!$inv instanceof Investissement || $inv->getStatus() !== 'PENDING') {
            $this->addFlash('danger', 'Demande introuvable ou déjà traitée.');
            return $this->redirectToRoute('backoffice_project_show', ['id' => $projectId]);
        }

        $project = $inv->getProject();
        if (!$project instanceof Project || $project->getId() !== $projectId) {
            $this->addFlash('danger', 'Projet introuvable.');
            return $this->redirectToRoute('backoffice_projects');
        }

        $inv->setStatus('REJECTED');
        $em->flush();
        $this->addFlash('success', 'Demande refusée.');

        return $this->redirectToRoute('backoffice_project_show', ['id' => $projectId]);
    }

    private function storeProjectUploadedImage(UploadedFile $file, int $projectId): string
    {
        $publicDir = $this->getParameter('kernel.project_dir') . '/public';
        $uploadDir = $publicDir . '/uploads/projects';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $ext = $file->guessExtension() ?: 'jpg';
        $safe = 'proj_' . $projectId . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $file->move($uploadDir, $safe);

        return 'uploads/projects/' . $safe;
    }

    // ==================== PRODUCTS CRUD ====================

    #[Route('/products', name: 'backoffice_products', methods: ['GET'])]
    public function products(EntityManagerInterface $em, Request $request): Response
    {
        $search = $request->query->get('search');
        $sort = (string) $request->query->get('sort', 'name');
        $dir = strtoupper((string) $request->query->get('dir', 'ASC'));

        $allowedSorts = ['name', 'pricePoints', 'stock'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'name';
        }
        if (!in_array($dir, ['ASC', 'DESC'], true)) {
            $dir = 'ASC';
        }

        $queryBuilder = $em->getRepository(Product::class)->createQueryBuilder('p');

        if ($search) {
            $queryBuilder->where('p.name LIKE :search')
                ->orWhere('p.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $queryBuilder->orderBy('p.' . $sort, $dir)
            ->addOrderBy('p.id', 'DESC');

        $products = $queryBuilder->getQuery()->getResult();

        return $this->render('backoffice/product/index.html.twig', [
            'products' => $products,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir
        ]);
    }

    #[Route('/products/create', name: 'backoffice_product_create', methods: ['GET', 'POST'])]
    public function createProduct(Request $request, EntityManagerInterface $em, FileUploadService $fileUploadService): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $imagePath = $fileUploadService->uploadImage($imageFile);
                $product->setImage($imagePath);
            }

            $em->persist($product);
            $em->flush();

            return $this->redirectToRoute('backoffice_products');
        }

        return $this->render('backoffice/product/create.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/products/{id}/edit', name: 'backoffice_product_edit', methods: ['GET', 'POST'])]
    public function editProduct(int $id, Request $request, EntityManagerInterface $em, FileUploadService $fileUploadService): Response
    {
        $product = $em->getRepository(Product::class)->find($id);
        if (!$product instanceof Product) {
            throw $this->createNotFoundException('Produit non trouvé');
        }

        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $oldImage = $product->getImage();
                if ($oldImage) {
                    $fileUploadService->deleteImage($oldImage);
                }
                $imagePath = $fileUploadService->uploadImage($imageFile);
                $product->setImage($imagePath);
            }

            $em->flush();

            return $this->redirectToRoute('backoffice_products');
        }

        return $this->render('backoffice/product/edit.html.twig', [
            'form' => $form,
            'product' => $product,
        ]);
    }

    #[Route('/products/{id}/delete', name: 'backoffice_product_delete', methods: ['POST'])]
    public function deleteProduct(int $id, Request $request, EntityManagerInterface $em, FileUploadService $fileUploadService): Response
    {
        if (!$this->isCsrfTokenValid('delete_product_' . $id, (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('backoffice_products');
        }

        $product = $em->getRepository(Product::class)->find($id);
        if (!$product instanceof Product) {
            return $this->redirectToRoute('backoffice_products');
        }

        if ($product->getImage()) {
            $fileUploadService->deleteImage($product->getImage());
        }

        $em->remove($product);
        $em->flush();

        return $this->redirectToRoute('backoffice_products');
    }

    // ==================== ADS CRUD ====================

    #[Route('/ads', name: 'backoffice_ads', methods: ['GET'])]
    public function ads(EntityManagerInterface $em, Request $request): Response
    {
        $search = $request->query->get('search');
        $sort = (string) $request->query->get('sort', 'title');
        $dir = strtoupper((string) $request->query->get('dir', 'ASC'));

        $allowedSorts = ['title', 'duration', 'rewardPoints'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'title';
        }
        if (!in_array($dir, ['ASC', 'DESC'], true)) {
            $dir = 'ASC';
        }

        $queryBuilder = $em->getRepository(Ad::class)->createQueryBuilder('a');

        if ($search) {
            $queryBuilder->where('a.title LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $queryBuilder->orderBy('a.' . $sort, $dir)
            ->addOrderBy('a.id', 'DESC');

        $ads = $queryBuilder->getQuery()->getResult();

        return $this->render('backoffice/ad/index.html.twig', [
            'ads' => $ads,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir
        ]);
    }

    #[Route('/ads/create', name: 'backoffice_ad_create', methods: ['GET', 'POST'])]
    public function createAd(Request $request, EntityManagerInterface $em, FileUploadService $fileUploadService): Response
    {
        $ad = new Ad();
        $form = $this->createForm(AdType::class, $ad);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imagePath')->getData();
            if ($imageFile) {
                $imagePath = $fileUploadService->uploadImage($imageFile);
                $ad->setImagePath($imagePath);
            }

            $em->persist($ad);
            $em->flush();

            return $this->redirectToRoute('backoffice_ads');
        }

        return $this->render('backoffice/ad/create.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/ads/{id}/edit', name: 'backoffice_ad_edit', methods: ['GET', 'POST'])]
    public function editAd(int $id, Request $request, EntityManagerInterface $em, FileUploadService $fileUploadService): Response
    {
        $ad = $em->getRepository(Ad::class)->find($id);
        if (!$ad instanceof Ad) {
            throw $this->createNotFoundException('Annonce non trouvée');
        }

        $form = $this->createForm(AdType::class, $ad);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imagePath')->getData();
            if ($imageFile) {
                $oldImage = $ad->getImagePath();
                if ($oldImage) {
                    $fileUploadService->deleteImage($oldImage);
                }
                $imagePath = $fileUploadService->uploadImage($imageFile);
                $ad->setImagePath($imagePath);
            }

            $em->flush();

            return $this->redirectToRoute('backoffice_ads');
        }

        return $this->render('backoffice/ad/edit.html.twig', [
            'form' => $form,
            'ad' => $ad,
        ]);
    }

    #[Route('/ads/{id}/delete', name: 'backoffice_ad_delete', methods: ['POST'])]
    public function deleteAd(int $id, Request $request, EntityManagerInterface $em, FileUploadService $fileUploadService): Response
    {
        if (!$this->isCsrfTokenValid('delete_ad_' . $id, (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('backoffice_ads');
        }

        $ad = $em->getRepository(Ad::class)->find($id);
        if (!$ad instanceof Ad) {
            return $this->redirectToRoute('backoffice_ads');
        }

        if ($ad->getImagePath()) {
            $fileUploadService->deleteImage($ad->getImagePath());
        }

        $em->remove($ad);
        $em->flush();

        return $this->redirectToRoute('backoffice_ads');
    }

    #[Route('/ads/{id}/clicks', name: 'backoffice_ad_clicks', methods: ['GET'])]
    public function adClicks(int $id, EntityManagerInterface $em): Response
    {
        $ad = $em->getRepository(Ad::class)->find($id);
        if (!$ad instanceof Ad) {
            throw $this->createNotFoundException('Annonce non trouvée');
        }

        $clicks = $em->getRepository(UserAdClick::class)
            ->findBy(['ad' => $ad], ['clickedAt' => 'DESC']);

        return $this->render('backoffice/ad/clicks.html.twig', [
            'ad' => $ad,
            'clicks' => $clicks,
        ]);
    }

    // ── Forum Management ──────────────────────────────────────────────────────

    #[Route('/forums', name: 'backoffice_forums', methods: ['GET'])]
    public function forums(EntityManagerInterface $em): Response
    {
        $forums = $em->getRepository(Forum::class)->findBy([], ['createdAt' => 'DESC']);
        return $this->render('backoffice/forums.html.twig', ['forums' => $forums]);
    }

    #[Route('/forums/{id}/delete', name: 'backoffice_forum_delete', methods: ['POST'])]
    public function forumDelete(int $id, EntityManagerInterface $em): Response
    {
        $forum = $em->getRepository(Forum::class)->find($id);
        if ($forum) {
            $em->remove($forum);
            $em->flush();
            $this->addFlash('success', 'Forum supprimé avec succès.');
        }
        return $this->redirectToRoute('backoffice_forums');
    }
}
