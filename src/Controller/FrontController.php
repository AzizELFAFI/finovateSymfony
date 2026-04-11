<?php

namespace App\Controller;

use App\Entity\Bill;
use App\Entity\Goal;
use App\Entity\Transaction;
use App\Entity\User;
use App\Form\CreateGoalRequestType;
use App\Form\GoalAmountRequestType;
use App\Form\PayBillRequestType;
use App\Form\TransferRequestType;
use App\Model\CreateGoalRequest;
use App\Model\GoalAmountRequest;
use App\Model\PayBillRequest;
use App\Model\TransferRequest;
use App\Entity\Product;
use App\Entity\Ad;
use App\Entity\UserAdClick;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

final class FrontController extends AbstractController
{
    #[Route('/', name: 'front_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('front/index.html.twig');
    }

    #[Route('/about', name: 'front_about', methods: ['GET'])]
    public function about(): Response
    {
        return $this->render('front/about.html.twig');
    }

    #[Route('/services', name: 'front_services', methods: ['GET'])]
    public function services(): Response
    {
        return $this->render('front/services.html.twig');
    }

    #[Route('/one-page', name: 'front_one_page', methods: ['GET'])]
    public function onePage(): Response
    {
        return $this->render('front/one-page.html.twig');
    }

    #[Route('/signup', name: 'front_signup', methods: ['GET'])]
    public function signup(): Response
    {
        return $this->render('front/signup.html.twig');
    }

    #[Route('/login', name: 'front_login', methods: ['GET'])]
    public function login(): Response
    {
        return $this->render('front/login.html.twig');
    }

    #[Route('/logout', name: 'front_logout', methods: ['GET'])]
    public function logout(): Response
    {
        $response = $this->render('front/logout.html.twig');
        $response->headers->setCookie(Cookie::create('finovate_token')->withValue('')->withExpires(1)->withPath('/'));
        return $response;
    }

    #[Route('/contact', name: 'front_contact', methods: ['GET'])]
    public function contact(): Response
    {
        return $this->render('front/contact.html.twig');
    }

    #[Route('/contact/send', name: 'front_contact_send', methods: ['POST'])]
    public function sendContact(Request $request, MailerInterface $mailer): Response
    {
        if (!$this->isCsrfTokenValid('contact_form', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Échec de validation du formulaire. Veuillez réessayer.');
            return $this->redirectToRoute('front_contact');
        }

        $name = trim((string) $request->request->get('name'));
        $fromEmail = trim((string) $request->request->get('email'));
        $subject = trim((string) $request->request->get('subject'));
        $message = trim((string) $request->request->get('message'));

        if ($name === '' || $fromEmail === '' || $subject === '' || $message === '') {
            $this->addFlash('danger', 'Veuillez remplir tous les champs du formulaire.');
            return $this->redirectToRoute('front_contact');
        }

        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('danger', 'Adresse e-mail invalide.');
            return $this->redirectToRoute('front_contact');
        }

        $email = (new Email())
            ->from('no-reply@finovate.tn')
            ->to('aziz.fafi@gmail.com')
            ->replyTo($fromEmail)
            ->subject('[Contact Finovate] ' . $subject)
            ->text("Nom: {$name}\nEmail: {$fromEmail}\n\nMessage:\n{$message}\n");

        try {
            $mailer->send($email);
            $this->addFlash('success', 'Votre message a été envoyé avec succès.');
        } catch (TransportExceptionInterface $e) {
            $this->addFlash('danger', "Impossible d'envoyer l'e-mail pour le moment. Veuillez réessayer plus tard.");
        }

        return $this->redirectToRoute('front_contact');
    }

    #[Route('/user/dashboard', name: 'user_dashboard', methods: ['GET'])]
    public function userDashboard(): Response
    {
        return $this->render('front/dashboard.html.twig');
    }

    #[Route('/user/transactions', name: 'user_transactions', methods: ['GET', 'POST'])]
    public function transactions(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('front_login');
        }

        $data = new TransferRequest();
        $form = $this->createForm(TransferRequestType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $beneficiaryCin = (string) $data->getCin();
            $amountStr = (string) $data->getMontant();
            $description = (string) $data->getDescription();

            $beneficiary = $em->getRepository(User::class)->findOneBy(['cin' => $beneficiaryCin]);
            if (!$beneficiary instanceof User) {
                $form->get('cin')->addError(new \Symfony\Component\Form\FormError("Bénéficiaire introuvable."));
            } elseif ($beneficiary->getId() === $user->getId()) {
                $form->get('cin')->addError(new \Symfony\Component\Form\FormError("Vous ne pouvez pas envoyer de l'argent à vous-même."));
            } else {
                $senderBalance = (float) str_replace(',', '.', (string) $user->getSolde());
                $amount = (float) str_replace(',', '.', $amountStr);

                if ($amount <= 0) {
                    $form->get('montant')->addError(new \Symfony\Component\Form\FormError("Montant invalide."));
                } elseif ($senderBalance < $amount) {
                    $form->get('montant')->addError(new \Symfony\Component\Form\FormError("Solde insuffisant."));
                } else {
                    $receiverBalance = (float) str_replace(',', '.', (string) $beneficiary->getSolde());
                    $user->setSolde((string) ($senderBalance - $amount));
                    $beneficiary->setSolde((string) ($receiverBalance + $amount));

                    $tx = new Transaction();
                    $tx->setSender_id((int) $user->getId());
                    $tx->setReceiver_id((int) $beneficiary->getId());
                    $tx->setAmount((string) $amount);
                    $tx->setType('TRANSFER');
                    $tx->setDescription($description);
                    $tx->setDate(new \DateTime());

                    $em->persist($tx);
                    $em->flush();

                    $this->addFlash('success', 'Virement effectué avec succès.');
                    return $this->redirectToRoute('user_transactions');
                }
            }
        }

        return $this->render('front/transactions.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/user/beneficiary-card', name: 'user_beneficiary_card', methods: ['GET'])]
    public function beneficiaryCard(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié.'], 401);
        }

        $cin = trim((string) $request->query->get('cin', ''));
        if ($cin === '' || !preg_match('/^\d{8}$/', $cin)) {
            return $this->json(['message' => 'CIN invalide.'], 422);
        }

        $beneficiary = $em->getRepository(User::class)->findOneBy(['cin' => $cin]);
        if (!$beneficiary instanceof User) {
            return $this->json(['message' => 'Bénéficiaire introuvable.'], 404);
        }

        return $this->json([
            'numero_carte' => $beneficiary->getNumero_carte(),
        ]);
    }

    #[Route('/user/goals', name: 'user_goals', methods: ['GET', 'POST'])]
    public function goals(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('front_login');
        }

        $createData = new CreateGoalRequest();
        $createForm = $this->createForm(CreateGoalRequestType::class, $createData);
        $createForm->handleRequest($request);

        if ($createForm->isSubmitted() && $createForm->isValid()) {
            $targetAmount = (float) str_replace(',', '.', (string) $createData->getTargetAmount());
            $goal = new Goal();

            // Trouver le premier ID libre (trou dans la séquence) à partir de 1
            $allIds = $em->createQueryBuilder()
                ->select('g.id')
                ->from(Goal::class, 'g')
                ->orderBy('g.id', 'ASC')
                ->getQuery()
                ->getSingleColumnResult();
            
            $generatedId = 1;
            foreach ($allIds as $id) {
                if ($id == $generatedId) {
                    $generatedId++;
                } elseif ($id > $generatedId) {
                    break;
                }
            }

            // Sécurité ultime contre la limite INT
            if ($generatedId > 2147483647) {
                $this->addFlash('danger', "Plus d'emplacements d'ID disponibles.");
                return $this->redirectToRoute('user_goals');
            }

            $goal->setId($generatedId);
            $goal->setId_user((int) $user->getId());
            $goal->setTitle((string) $createData->getTitle());
            $goal->setDeadline($createData->getDeadline());
            $goal->setCreated_at(new \DateTime());
            $goal->setStatus('IN_PROGRESS');
            $goal->setTarget_amount((string) $targetAmount);
            $goal->setCurrent_amount('0');

            $em->persist($goal);
            $em->flush();

            $this->addFlash('success', 'Goal ajouté avec succès.');
            return $this->redirectToRoute('user_goals');
        }

        $goals = $em->getRepository(Goal::class)->findBy(
            ['id_user' => (int) $user->getId()],
            ['created_at' => 'DESC']
        );

        $amountForms = [];
        foreach ($goals as $g) {
            $amountData = new GoalAmountRequest();
            $amountForms[$g->getId()] = $this->createForm(GoalAmountRequestType::class, $amountData, [
                'action' => $this->generateUrl('user_goal_add_amount', ['id' => $g->getId()]),
                'method' => 'POST',
            ])->createView();
        }

        return $this->render('front/goals.html.twig', [
            'createForm' => $createForm,
            'goals' => $goals,
            'amountForms' => $amountForms,
        ]);
    }

    #[Route('/user/goals/{id}/amount', name: 'user_goal_add_amount', methods: ['POST'])]
    public function addGoalAmount(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('front_login');
        }

        $goal = $em->getRepository(Goal::class)->find($id);
        if (!$goal instanceof Goal || (int) $goal->getId_user() !== (int) $user->getId()) {
            $this->addFlash('danger', 'Goal introuvable.');
            return $this->redirectToRoute('user_goals');
        }

        if ($goal->getStatus() === 'COMPLETED') {
            $this->addFlash('danger', 'Ce goal est déjà terminé.');
            return $this->redirectToRoute('user_goals');
        }

        $data = new GoalAmountRequest();
        $form = $this->createForm(GoalAmountRequestType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $delta = (float) str_replace(',', '.', (string) $data->getAmount());
            $userBalance = (float) str_replace(',', '.', (string) $user->getSolde());

            if ($userBalance < $delta) {
                $this->addFlash('danger', 'Solde insuffisant pour ajouter ce montant.');
                return $this->redirectToRoute('user_goals');
            }

            $current = (float) str_replace(',', '.', (string) $goal->getCurrent_amount());
            $target = (float) str_replace(',', '.', (string) $goal->getTarget_amount());

            $newValue = $current + $delta;

            $goal->setCurrent_amount((string) $newValue);
            if ($newValue >= $target) {
                $goal->setStatus('COMPLETED');
            }

            $user->setSolde((string) ($userBalance - $delta));

            $em->flush();
            $this->addFlash('success', 'Montant ajouté et solde mis à jour.');
        } else {
            $this->addFlash('danger', 'Montant invalide.');
        }

        return $this->redirectToRoute('user_goals');
    }

    #[Route('/user/goals/{id}/delete', name: 'user_goal_delete', methods: ['POST'])]
    public function deleteGoal(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('front_login');
        }

        $goal = $em->getRepository(Goal::class)->find($id);
        if (!$goal instanceof Goal || (int) $goal->getId_user() !== (int) $user->getId()) {
            $this->addFlash('danger', 'Goal introuvable.');
            return $this->redirectToRoute('user_goals');
        }

        if (!$this->isCsrfTokenValid('delete_goal_' . $goal->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Échec de validation.');
            return $this->redirectToRoute('user_goals');
        }

        // Restitution du montant accumulé au solde
        $currentAccumulated = (float) str_replace(',', '.', (string) $goal->getCurrent_amount());
        if ($currentAccumulated > 0) {
            $userBalance = (float) str_replace(',', '.', (string) $user->getSolde());
            $user->setSolde((string) ($userBalance + $currentAccumulated));
        }

        $em->remove($goal);
        $em->flush();

        $this->addFlash('success', 'Goal supprimé et montant restitué au solde.');
        return $this->redirectToRoute('user_goals');
    }

    #[Route('/user/bills', name: 'user_bills', methods: ['GET', 'POST'])]
    public function bills(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('front_login');
        }

        $data = new PayBillRequest();
        $form = $this->createForm(PayBillRequestType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $amount = (float) str_replace(',', '.', (string) $data->getAmount());
            $balance = (float) str_replace(',', '.', (string) $user->getSolde());

            if ($balance < $amount) {
                $form->get('amount')->addError(new \Symfony\Component\Form\FormError('Solde insuffisant.'));
            } else {
                $bill = new Bill();

                $maxIdScalar = $em->createQueryBuilder()
                    ->select('MAX(b.id)')
                    ->from(Bill::class, 'b')
                    ->getQuery()
                    ->getSingleScalarResult();
                $maxId = $maxIdScalar !== null ? (int) $maxIdScalar : 0;
                $generatedId = $maxId + 1;

                $bill->setId($generatedId);
                $bill->setId_user((int) $user->getId());
                $bill->setReference((string) $data->getReference());
                $bill->setAmount((float) $amount);
                $bill->setDate_paiement(new \DateTime());

                $user->setSolde((string) ($balance - $amount));

                $em->persist($bill);
                $em->flush();

                $this->addFlash('success', 'Facture payée avec succès.');
                return $this->redirectToRoute('user_bills');
            }
        }

        $bills = $em->getRepository(Bill::class)->findBy(
            ['id_user' => (int) $user->getId()],
            ['date_paiement' => 'DESC']
        );

        return $this->render('front/bills.html.twig', [
            'form' => $form,
            'bills' => $bills,
        ]);
    }


    // ==================== PRODUCTS ====================

    #[Route('/user/products', name: 'front_products', methods: ['GET'])]
    public function productsList(Request $request, EntityManagerInterface $em): Response
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

        return $this->render('front/product/list.html.twig', [
            'products' => $products,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir
        ]);
    }

    #[Route('/user/products/{id}', name: 'front_product_detail', methods: ['GET'])]
    public function productDetail(int $id, EntityManagerInterface $em): Response
    {
        $product = $em->getRepository(Product::class)->find($id);
        if (!$product instanceof Product) {
            throw $this->createNotFoundException('Produit non trouvé');
        }

        return $this->render('front/product/detail.html.twig', [
            'product' => $product,
        ]);
    }

    // ==================== ADS ====================

    #[Route('/user/ads', name: 'front_ads', methods: ['GET'])]
    public function adsList(Request $request, EntityManagerInterface $em): Response
    {
        $search = $request->query->get('search');

        $queryBuilder = $em->getRepository(Ad::class)->createQueryBuilder('a');

        if ($search) {
            $queryBuilder->where('a.title LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $queryBuilder->orderBy('a.id', 'DESC');

        $ads = $queryBuilder->getQuery()->getResult();

        return $this->render('front/ad/list.html.twig', [
            'ads' => $ads,
            'search' => $search
        ]);
    }

    #[Route('/user/ads/{id}', name: 'front_ad_detail', methods: ['GET'])]
    public function adDetail(int $id, EntityManagerInterface $em): Response
    {
        $ad = $em->getRepository(Ad::class)->find($id);
        if (!$ad instanceof Ad) {
            throw $this->createNotFoundException('Annonce non trouvée');
        }

        return $this->render('front/ad/detail.html.twig', [
            'ad' => $ad,
        ]);
    }

    #[Route('/user/ads/{id}/click', name: 'front_ad_click', methods: ['POST'])]
    public function adClick(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentification requise'], 401);
        }

        if (!$this->isCsrfTokenValid('ad_click_' . $id, (string) $request->request->get('_token'))) {
            return $this->json(['error' => 'Token CSRF invalide'], 400);
        }

        $ad = $em->getRepository(Ad::class)->find($id);
        if (!$ad instanceof Ad) {
            return $this->json(['error' => 'Annonce non trouvée'], 404);
        }

        // Vérifier si l'utilisateur a déjà cliqué sur cette annonce aujourd'hui
        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        $existingClick = $em->getRepository(UserAdClick::class)
            ->createQueryBuilder('uac')
            ->where('uac.user = :user')
            ->andWhere('uac.ad = :ad')
            ->andWhere('uac.clickedAt >= :today')
            ->setParameter('user', $user)
            ->setParameter('ad', $ad)
            ->setParameter('today', $today)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existingClick instanceof UserAdClick) {
            return $this->json(['message' => 'Vous avez déjà cliqué sur cette annonce aujourd\'hui'], 400);
        }

        // Créer le clic
        $click = new UserAdClick();
        $click->setUser($user);
        $click->setAd($ad);
        $click->setClickedAt(new \DateTime());

        // Ajouter les points à l'utilisateur
        $userPoints = (int) $user->getPoints();
        $rewardPoints = (int) $ad->getRewardPoints();
        $user->setPoints($userPoints + $rewardPoints);

        $em->persist($click);
        $em->flush();

        return $this->json([
            'message' => 'Clic enregistré avec succès',
            'rewardPoints' => $rewardPoints
        ], 200);
    }
}
