<?php

namespace App\Controller;

use App\Entity\Investissement;
use App\Entity\Project;
use App\Entity\User;
use App\Form\CreateProjectRequestType;
use App\Form\EditProjectRequestType;
use App\Model\CreateProjectRequest;
use App\Model\EditProjectRequest;
use App\Repository\InvestissementRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InvestissementController extends AbstractController
{
    #[Route('/investissement', name: 'investissement_index', methods: ['GET'])]
    public function index(
        ProjectRepository $projectRepository,
        InvestissementRepository $investissementRepository,
        Request $request,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('front_login');
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $q = trim((string) $request->query->get('q', ''));
        $status = trim((string) $request->query->get('status', ''));
        $sort = trim((string) $request->query->get('sort', 'id_desc'));

        [$projects, $total] = $projectRepository->findPaginatedFiltered($page, 10, $q, $status, $sort);
        $perPage = 10;
        $pages = max(1, (int) ceil($total / $perPage));

        return $this->renderInvestissementPage(
            $user,
            $projectRepository,
            $investissementRepository,
            $this->createForm(CreateProjectRequestType::class, new CreateProjectRequest()),
            $this->createForm(EditProjectRequestType::class, new EditProjectRequest()),
            $request->query->getBoolean('nouveau'),
            $request->query->getBoolean('gerer'),
            false,
            null,
            $projects,
            [
                'page' => $page,
                'pages' => $pages,
                'total' => $total,
                'perPage' => $perPage,
            ],
            $q,
            $status,
            $sort,
        );
    }

    #[Route('/investissement/mes-demandes', name: 'investissement_mes_demandes', methods: ['GET'])]
    public function mesDemandes(InvestissementRepository $investissementRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('front_login');
        }

        $demandes = $investissementRepository->findByInvestorOrdered($user);

        return $this->render('front/investissement_mes_demandes.html.twig', [
            'demandes' => $demandes,
        ]);
    }

    #[Route('/investissement/mes-projets', name: 'investissement_my_projects', methods: ['GET'])]
    public function myProjectsLegacyRedirect(): Response
    {
        return $this->redirectToRoute('investissement_index', ['gerer' => 1]);
    }

    #[Route('/investissement/projet', name: 'investissement_project_create', methods: ['POST'])]
    public function createProject(
        Request $request,
        EntityManagerInterface $em,
        ProjectRepository $projectRepository,
        InvestissementRepository $investissementRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('front_login');
        }

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
                if ($deadline instanceof \DateTimeImmutable) {
                    $project->setDeadline(\DateTime::createFromImmutable($deadline));
                } else {
                    $project->setDeadline($deadline);
                }
            }
            $category = trim((string) ($data->getCategory() ?? ''));
            $project->setCategory($category !== '' ? $category : null);
            $project->setOwner($user);

            $em->persist($project);
            $em->flush();

            $uploaded = $data->getImage();
            if ($uploaded instanceof UploadedFile) {
                $project->setImagePath($this->storeProjectUploadedImage($uploaded, $project->getId()));
                $em->flush();
            }

            $this->addFlash('success', 'Projet créé avec succès.');

            return $this->redirectToRoute('investissement_index');
        }

        return $this->renderInvestissementPageFromRequest(
            $user,
            $projectRepository,
            $investissementRepository,
            $request,
            $form,
            $this->createForm(EditProjectRequestType::class, new EditProjectRequest()),
            $form->isSubmitted(),
            false,
            false,
            null,
        );
    }

    #[Route('/investissement/projet/{id}/modifier', name: 'investissement_project_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function editProject(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        ProjectRepository $projectRepository,
        InvestissementRepository $investissementRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('front_login');
        }

        $project = $em->getRepository(Project::class)->find($id);
        if (!$project instanceof Project || $project->getOwner()?->getId() !== $user->getId()) {
            $this->addFlash('danger', 'Projet introuvable ou accès refusé.');

            return $this->redirectToRoute('investissement_index', ['gerer' => 1]);
        }

        $data = new EditProjectRequest();
        $form = $this->createForm(EditProjectRequestType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $goal = (float) str_replace(',', '.', (string) $data->getGoalAmount());
            $project->setTitle((string) $data->getTitle());
            $project->setDescription((string) $data->getDescription());
            $project->setGoalAmount(number_format($goal, 2, '.', ''));
            $deadline = $data->getDeadline();
            if ($deadline instanceof \DateTimeInterface) {
                $project->setDeadline(\DateTime::createFromInterface($deadline));
            } else {
                $project->setDeadline(null);
            }
            $category = trim((string) ($data->getCategory() ?? ''));
            $project->setCategory($category !== '' ? $category : null);

            $newImage = $data->getImage();
            if ($newImage instanceof UploadedFile) {
                $this->removeProjectImageFile($project->getImagePath());
                $project->setImagePath($this->storeProjectUploadedImage($newImage, $project->getId()));
            }

            $em->flush();
            $this->addFlash('success', 'Projet mis à jour.');

            return $this->redirectToRoute('investissement_index', ['gerer' => 1]);
        }

        return $this->renderInvestissementPageFromRequest(
            $user,
            $projectRepository,
            $investissementRepository,
            $request,
            $this->createForm(CreateProjectRequestType::class, new CreateProjectRequest()),
            $form,
            false,
            true,
            true,
            $id,
        );
    }

    #[Route('/investissement/projet/{id}/supprimer', name: 'investissement_project_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteProject(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('front_login');
        }

        if (!$this->isCsrfTokenValid('delete_project_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Échec de validation du formulaire.');

            return $this->redirectToRoute('investissement_index', ['gerer' => 1]);
        }

        $project = $em->getRepository(Project::class)->find($id);
        if (!$project instanceof Project || $project->getOwner()?->getId() !== $user->getId()) {
            $this->addFlash('danger', 'Projet introuvable ou accès refusé.');

            return $this->redirectToRoute('investissement_index', ['gerer' => 1]);
        }

        $this->removeProjectImageFile($project->getImagePath());

        foreach ($em->getRepository(Investissement::class)->findBy(['project' => $project]) as $investissement) {
            $em->remove($investissement);
        }
        $em->remove($project);
        $em->flush();

        $this->addFlash('success', 'Projet et investissements associés ont été supprimés.');

        return $this->redirectToRoute('investissement_index', ['gerer' => 1]);
    }

    #[Route('/investissement/{id}/invest', name: 'investissement_invest', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function invest(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        InvestissementRepository $investissementRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('front_login');
        }

        if (!$this->isCsrfTokenValid('investissement_invest', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Échec de validation du formulaire.');

            return $this->redirectToRoute('investissement_index');
        }

        $project = $em->getRepository(Project::class)->find($id);
        if (!$project instanceof Project) {
            $this->addFlash('danger', 'Projet introuvable.');

            return $this->redirectToRoute('investissement_index');
        }

        if ($project->getOwner()?->getId() === $user->getId()) {
            $this->addFlash('danger', 'Vous ne pouvez pas investir dans votre propre projet.');

            return $this->redirectToRoute('investissement_index');
        }

        if ($investissementRepository->findOnePendingByUserAndProject($user, $project) instanceof Investissement) {
            $this->addFlash('warning', 'Vous avez déjà une demande en attente pour ce projet. Modifiez-la ou annulez-la depuis Mes demandes.');

            return $this->redirectToRoute('investissement_index');
        }

        $amountStr = trim((string) $request->request->get('amount', ''));
        $amount = (float) str_replace(',', '.', $amountStr);

        if ($amount <= 0) {
            $this->addFlash('danger', 'Montant invalide.');

            return $this->redirectToRoute('investissement_index');
        }

        $investissement = new Investissement();
        $investissement->setAmount(number_format($amount, 2, '.', ''));
        $investissement->setInvestmentDate(new \DateTimeImmutable());
        $investissement->setStatus('PENDING');
        $investissement->setProject($project);
        $investissement->setUser($user);

        $em->persist($investissement);
        $em->flush();

        $this->addFlash('success', 'Demande d’investissement envoyée. Le propriétaire du projet doit l’accepter.');

        return $this->redirectToRoute('investissement_index');
    }

    #[Route('/investissement/demande/{id}/accepter', name: 'investissement_demande_accepter', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function accepterDemande(int $id, Request $request, EntityManagerInterface $em, InvestissementRepository $investissementRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('front_login');
        }

        if (!$this->isCsrfTokenValid('invest_demande_owner_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Échec de validation du formulaire.');

            return $this->redirectToRoute('investissement_index', ['gerer' => 1]);
        }

        $inv = $investissementRepository->find($id);
        if (!$inv instanceof Investissement || $inv->getStatus() !== 'PENDING') {
            $this->addFlash('danger', 'Demande introuvable ou déjà traitée.');

            return $this->redirectToRoute('investissement_index', ['gerer' => 1]);
        }

        $project = $inv->getProject();
        if (!$project instanceof Project || $project->getOwner()?->getId() !== $user->getId()) {
            $this->addFlash('danger', 'Accès refusé.');

            return $this->redirectToRoute('investissement_index', ['gerer' => 1]);
        }

        $investor = $inv->getUser();
        if (!$investor instanceof User) {
            $this->addFlash('danger', 'Investisseur introuvable.');

            return $this->redirectToRoute('investissement_index', ['gerer' => 1]);
        }

        $amount = (float) str_replace(',', '.', (string) $inv->getAmount());
        $balance = (float) str_replace(',', '.', (string) $investor->getSolde());
        if ($balance < $amount) {
            $this->addFlash('danger', 'Le solde de l’investisseur est insuffisant pour confirmer ce montant.');

            return $this->redirectToRoute('investissement_index', ['gerer' => 1]);
        }

        $investor->setSolde((string) ($balance - $amount));
        $current = (float) str_replace(',', '.', (string) ($project->getCurrentAmount() ?? '0'));
        $project->setCurrentAmount((string) ($current + $amount));
        $inv->setStatus('CONFIRMED');

        $em->flush();

        $this->addFlash('success', 'Demande acceptée. Le montant a été débité et ajouté au projet.');

        return $this->redirectToRoute('investissement_index', ['gerer' => 1]);
    }

    #[Route('/investissement/demande/{id}/refuser', name: 'investissement_demande_refuser', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function refuserDemande(int $id, Request $request, EntityManagerInterface $em, InvestissementRepository $investissementRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('front_login');
        }

        if (!$this->isCsrfTokenValid('invest_demande_owner_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Échec de validation du formulaire.');

            return $this->redirectToRoute('investissement_index', ['gerer' => 1]);
        }

        $inv = $investissementRepository->find($id);
        if (!$inv instanceof Investissement || $inv->getStatus() !== 'PENDING') {
            $this->addFlash('danger', 'Demande introuvable ou déjà traitée.');

            return $this->redirectToRoute('investissement_index', ['gerer' => 1]);
        }

        $project = $inv->getProject();
        if (!$project instanceof Project || $project->getOwner()?->getId() !== $user->getId()) {
            $this->addFlash('danger', 'Accès refusé.');

            return $this->redirectToRoute('investissement_index', ['gerer' => 1]);
        }

        $inv->setStatus('REJECTED');
        $em->flush();

        $this->addFlash('success', 'Demande refusée.');

        return $this->redirectToRoute('investissement_index', ['gerer' => 1]);
    }

    #[Route('/investissement/demande/{id}/montant', name: 'investissement_demande_montant', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function modifierMontantDemande(int $id, Request $request, EntityManagerInterface $em, InvestissementRepository $investissementRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('front_login');
        }

        if (!$this->isCsrfTokenValid('invest_demande_investor_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Échec de validation du formulaire.');

            return $this->redirectToRoute('investissement_mes_demandes');
        }

        $inv = $investissementRepository->find($id);
        if (!$inv instanceof Investissement || $inv->getUser()?->getId() !== $user->getId() || $inv->getStatus() !== 'PENDING') {
            $this->addFlash('danger', 'Modification impossible.');

            return $this->redirectToRoute('investissement_mes_demandes');
        }

        $amountStr = trim((string) $request->request->get('amount', ''));
        $amount = (float) str_replace(',', '.', $amountStr);
        if ($amount <= 0) {
            $this->addFlash('danger', 'Montant invalide.');

            return $this->redirectToRoute('investissement_mes_demandes');
        }

        $inv->setAmount(number_format($amount, 2, '.', ''));
        $em->flush();

        $this->addFlash('success', 'Montant de la demande mis à jour.');

        return $this->redirectToRoute('investissement_mes_demandes');
    }

    #[Route('/investissement/demande/{id}/annuler', name: 'investissement_demande_annuler', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function annulerDemande(int $id, Request $request, EntityManagerInterface $em, InvestissementRepository $investissementRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('front_login');
        }

        if (!$this->isCsrfTokenValid('invest_demande_investor_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Échec de validation du formulaire.');

            return $this->redirectToRoute('investissement_mes_demandes');
        }

        $inv = $investissementRepository->find($id);
        if (!$inv instanceof Investissement || $inv->getUser()?->getId() !== $user->getId() || $inv->getStatus() !== 'PENDING') {
            $this->addFlash('danger', 'Annulation impossible.');

            return $this->redirectToRoute('investissement_mes_demandes');
        }

        $em->remove($inv);
        $em->flush();

        $this->addFlash('success', 'Demande annulée.');

        return $this->redirectToRoute('investissement_mes_demandes');
    }

    private function renderInvestissementPageFromRequest(
        User $user,
        ProjectRepository $projectRepository,
        InvestissementRepository $investissementRepository,
        Request $request,
        FormInterface $createForm,
        FormInterface $editProjectForm,
        bool $showProjectFormModal,
        bool $openManageProjectsModal,
        bool $openEditProjectPanel,
        ?int $editProjectIdForAction,
    ): Response {
        $page = max(1, (int) $request->query->get('page', 1));
        $q = trim((string) $request->query->get('q', ''));
        $status = trim((string) $request->query->get('status', ''));
        $sort = trim((string) $request->query->get('sort', 'id_desc'));

        [$projects, $total] = $projectRepository->findPaginatedFiltered($page, 10, $q, $status, $sort);
        $perPage = 10;
        $pages = max(1, (int) ceil($total / $perPage));

        return $this->renderInvestissementPage(
            $user,
            $projectRepository,
            $investissementRepository,
            $createForm,
            $editProjectForm,
            $showProjectFormModal,
            $openManageProjectsModal,
            $openEditProjectPanel,
            $editProjectIdForAction,
            $projects,
            [
                'page' => $page,
                'pages' => $pages,
                'total' => $total,
                'perPage' => $perPage,
            ],
            $q,
            $status,
            $sort,
        );
    }

    private function renderInvestissementPage(
        User $user,
        ProjectRepository $projectRepository,
        InvestissementRepository $investissementRepository,
        FormInterface $createForm,
        FormInterface $editProjectForm,
        bool $showProjectFormModal,
        bool $openManageProjectsModal,
        bool $openEditProjectPanel,
        ?int $editProjectIdForAction,
        array $projects,
        array $pagination,
        string $filterQ,
        string $filterStatus,
        string $filterSort,
    ): Response {
        $myProjects = $projectRepository->findBy(['owner' => $user], ['id' => 'DESC']);

        $pendingByProject = [];
        foreach ($myProjects as $p) {
            $pendingByProject[$p->getId()] = $investissementRepository->findPendingByProject($p);
        }

        $payload = [];
        foreach ($myProjects as $p) {
            $payload[] = [
                'id' => $p->getId(),
                'title' => $p->getTitle(),
                'description' => $p->getDescription(),
                'goalAmount' => $p->getGoalAmount(),
                'deadline' => $p->getDeadline()?->format('Y-m-d'),
                'category' => $p->getCategory() ?? '',
                'imagePath' => $p->getImagePath(),
            ];
        }

        return $this->render('front/investissement.html.twig', [
            'projects' => $projects,
            'myProjects' => $myProjects,
            'pendingByProject' => $pendingByProject,
            'myProjectsDataJson' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
            'createForm' => $createForm,
            'editProjectForm' => $editProjectForm,
            'showProjectFormModal' => $showProjectFormModal,
            'openManageProjectsModal' => $openManageProjectsModal || $openEditProjectPanel,
            'openEditProjectPanel' => $openEditProjectPanel,
            'editProjectIdForAction' => $editProjectIdForAction,
            'pagination' => $pagination,
            'filterQ' => $filterQ,
            'filterStatus' => $filterStatus,
            'filterSort' => $filterSort,
        ]);
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

    private function removeProjectImageFile(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '' || str_contains($relativePath, '..')) {
            return;
        }
        $full = $this->getParameter('kernel.project_dir') . '/public/' . $relativePath;
        if (is_file($full)) {
            @unlink($full);
        }
    }
}
