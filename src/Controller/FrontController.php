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
use App\Service\PdfService;
use App\Service\QRCodeService;
use App\Service\StripeService;
use App\Service\TwilioService;
use App\Service\ProductPurchaseService;
use App\Service\ProductRatingService;
use App\Service\AdRatingService;
use App\Service\ProductFavoriteService;
use App\Service\CartService;
use App\Service\AIRecommendationService;
use Knp\Component\Pager\PaginatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

final class FrontController extends AbstractController
{
    #[Route('/', name: 'front_home', methods: ['GET'])]
    public function home(): Response
    {
        $template = $this->getUser() ? 'front/index.html.twig' : 'front/index_guest.html.twig';
        return $this->render($template);
    }

    #[Route('/about', name: 'front_about', methods: ['GET'])]
    public function about(): Response
    {
        $template = $this->getUser() ? 'front/about.html.twig' : 'front/about_guest.html.twig';
        return $this->render($template);
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
    public function logout(Request $request): Response
    {
        // Clear the session
        $session = $request->getSession();
        $session->invalidate();
        
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
    public function transactions(Request $request, EntityManagerInterface $em, TwilioService $twilioService): Response
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
                    $this->addFlash('danger', "Solde insuffisant. Votre solde actuel est de " . number_format($senderBalance, 2, ',', ' ') . ".");
                    return $this->redirectToRoute('user_transactions');
                } else {
                    // Check daily limit (3000)
                    $transactionRepo = $em->getRepository(Transaction::class);
                    $sentToday = $transactionRepo->getTotalSentToday((int) $user->getId());
                    $dailyLimit = 3000.0;
                    
                    if (($sentToday + $amount) > $dailyLimit) {
                        $remaining = max(0, $dailyLimit - $sentToday);
                        $this->addFlash('danger', "Limite journalière de 3000 dépassée. Vous avez déjà envoyé " . number_format($sentToday, 2, ',', ' ') . " aujourd'hui. Restant: " . number_format($remaining, 2, ',', ' ') . ".");
                        return $this->redirectToRoute('user_transactions');
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

                    try {
                        $to = $twilioService->formatTunisiaNumber($beneficiary->getPhone_number());
                        $message = sprintf(
                            'FINOVATE: Vous avez reçu un virement de %s %s. Montant: %s TND. Réf: TRX-%s.',
                            $user->getFirstname(),
                            $user->getLastname(),
                            number_format($amount, 2, ',', ' '),
                            str_pad((string) $tx->getId(), 8, '0', STR_PAD_LEFT)
                        );
                        $twilioService->sendSms($to, $message);
                    } catch (\Throwable $e) {
                    }

                    // Generate PDF receipt and save to uploads
                    $receiptsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/receipts';
                    if (!is_dir($receiptsDir)) {
                        mkdir($receiptsDir, 0777, true);
                    }

                    $pdfService = new PdfService($this->container->get('twig'));
                    $pdfContent = $pdfService->generateTransactionReceipt([
                        'logo_path' => $this->getParameter('kernel.project_dir') . '/public/logo.png',
                        'reference' => 'TRX-' . str_pad((string) $tx->getId(), 8, '0', STR_PAD_LEFT),
                        'sender_firstname' => $user->getFirstname(),
                        'sender_lastname' => $user->getLastname(),
                        'sender_email' => $user->getEmail(),
                        'sender_cin' => $user->getCin(),
                        'beneficiary_firstname' => $beneficiary->getFirstname(),
                        'beneficiary_lastname' => $beneficiary->getLastname(),
                        'beneficiary_cin' => $beneficiary->getCin(),
                        'beneficiary_card_number' => $beneficiary->getNumero_carte(),
                        'amount' => number_format($amount, 2, ',', ' '),
                        'date' => $tx->getDate()->format('d/m/Y'),
                        'time' => $tx->getDate()->format('H:i:s'),
                        'description' => $description ?: 'Virement bancaire',
                    ]);

                    $pdfFilename = 'receipt_' . $tx->getId() . '.pdf';
                    file_put_contents($receiptsDir . '/' . $pdfFilename, $pdfContent);

                    $this->addFlash('success', 'Virement effectué avec succès. <a href="/uploads/receipts/' . $pdfFilename . '" class="alert-link" download>Télécharger le reçu PDF</a>');
                    return $this->redirectToRoute('user_transactions');
                    }
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

    #[Route('/products', name: 'front_products_public', methods: ['GET'])]
    public function productsPublic(Request $request, EntityManagerInterface $em, PaginatorInterface $paginator): Response
    {
        $search = $request->query->get('search');
        $sortBy = (string) $request->query->get('sortBy', 'name');
        $order = strtoupper((string) $request->query->get('order', 'ASC'));

        $allowedSorts = ['name', 'pricePoints', 'stock'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'name';
        }
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'ASC';
        }

        $queryBuilder = $em->getRepository(Product::class)->createQueryBuilder('p');

        if ($search) {
            $queryBuilder->where('p.name LIKE :search')
                ->orWhere('p.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $queryBuilder->orderBy('p.' . $sortBy, $order)
            ->addOrderBy('p.id', 'DESC');

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            3,
            ['sortable' => false]
        );

        return $this->render('front/product/list.html.twig', [
            'pagination' => $pagination,
            'search' => $search,
            'sortBy' => $sortBy,
            'order' => $order,
            'is_public' => true,
        ]);
    }

    #[Route('/user/products', name: 'front_products', methods: ['GET'])]
    public function productsList(Request $request, EntityManagerInterface $em, PaginatorInterface $paginator): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('front_products_public');
        }

        $search = $request->query->get('search');
        $sortBy = (string) $request->query->get('sortBy', 'name');
        $order = strtoupper((string) $request->query->get('order', 'ASC'));

        $allowedSorts = ['name', 'pricePoints', 'stock'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'name';
        }
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'ASC';
        }

        $queryBuilder = $em->getRepository(Product::class)->createQueryBuilder('p');

        if ($search) {
            $queryBuilder->where('p.name LIKE :search')
                ->orWhere('p.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $queryBuilder->orderBy('p.' . $sortBy, $order)
            ->addOrderBy('p.id', 'DESC');

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            3,
            ['sortable' => false]
        );

        return $this->render('front/product/list.html.twig', [
            'pagination' => $pagination,
            'search' => $search,
            'sortBy' => $sortBy,
            'order' => $order,
            'is_public' => false,
        ]);
    }

    #[Route('/user/products/favorites', name: 'front_product_favorites', methods: ['GET'])]
    public function productFavorites(
        EntityManagerInterface $em,
        ProductFavoriteService $favoriteService
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('front_login');
        }

        $favorites = $favoriteService->getUserFavorites($user->getId());
        $products = [];

        foreach ($favorites as $favorite) {
            $product = $em->getRepository(Product::class)->find($favorite->getProductId());
            if ($product) {
                $products[] = [
                    'product' => $product,
                    'favorite' => $favorite,
                ];
            }
        }

        return $this->render('front/product/favorites.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/user/products/{id}', name: 'front_product_detail', methods: ['GET'])]
    public function productDetail(int $id, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('front_products_public');
        }

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
    public function adsList(Request $request, EntityManagerInterface $em, PaginatorInterface $paginator, AIRecommendationService $aiService): Response
    {
        $user = $this->getUser();
        $search = $request->query->get('search');
        $sortBy = (string) $request->query->get('sortBy', 'title');
        $order = strtoupper((string) $request->query->get('order', 'ASC'));
        $useAI = $request->query->get('ai', '1') === '1';

        $allowedSorts = ['title', 'rewardPoints', 'duration'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'title';
        }
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'ASC';
        }

        // Get AI recommendations if enabled and user is logged in
        $recommendedAds = [];
        $aiAnalysis = null;
        if ($useAI && $user instanceof User) {
            try {
                $recommendedAds = $aiService->getRecommendedAds($user, 3);
                $aiAnalysis = $aiService->getUserAnalysis($user);
            } catch (\Throwable $e) {
                // AI failed, continue without recommendations
            }
        }

        $queryBuilder = $em->getRepository(Ad::class)->createQueryBuilder('a');

        if ($search) {
            $queryBuilder->where('a.title LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $queryBuilder->orderBy('a.' . $sortBy, $order)
            ->addOrderBy('a.id', 'DESC');

        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            3,
            ['sortable' => false]
        );

        return $this->render('front/ad/list.html.twig', [
            'pagination' => $pagination,
            'search' => $search,
            'sortBy' => $sortBy,
            'order' => $order,
            'recommendedAds' => $recommendedAds,
            'aiAnalysis' => $aiAnalysis,
            'useAI' => $useAI,
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

    #[Route('/user/topup', name: 'user_topup', methods: ['GET'])]
    public function topupPage(): Response
    {
        return $this->render('front/topup.html.twig');
    }

    #[Route('/api/stripe/create-payment-intent', name: 'api_stripe_create_payment_intent', methods: ['POST'])]
    public function createStripePaymentIntent(Request $request, StripeService $stripeService): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié.'], 401);
        }

        $data = json_decode($request->getContent() ?: '', true);
        $amount = (float) ($data['amount'] ?? 0);

        if ($amount <= 0 || $amount < 1) {
            return $this->json(['message' => 'Le montant doit être supérieur à 1 TND.'], 400);
        }

        if ($amount > 10000) {
            return $this->json(['message' => 'Le montant maximum est de 10 000 TND.'], 400);
        }

        try {
            $result = $stripeService->createPaymentIntent($amount);
            $result['publishable_key'] = $stripeService->getPublishableKey();
            return $this->json($result);
        } catch (\Throwable $e) {
            return $this->json(['message' => 'Erreur lors de la création du paiement: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/api/stripe/confirm-payment', name: 'api_stripe_confirm_payment', methods: ['POST'])]
    public function confirmStripePayment(Request $request, StripeService $stripeService): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié.'], 401);
        }

        $data = json_decode($request->getContent() ?: '', true);
        $paymentIntentId = (string) ($data['payment_intent_id'] ?? '');

        if (empty($paymentIntentId)) {
            return $this->json(['message' => 'ID de paiement manquant.'], 400);
        }

        try {
            $result = $stripeService->confirmPaymentAndCredit($paymentIntentId, $user);
            return $this->json($result, $result['success'] ? 200 : 400);
        } catch (\Throwable $e) {
            return $this->json(['message' => 'Erreur lors de la confirmation: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/user/buy-points', name: 'user_buy_points', methods: ['GET'])]
    public function buyPointsPage(): Response
    {
        return $this->render('front/buy-points.html.twig');
    }

    #[Route('/api/stripe/create-points-payment-intent', name: 'api_stripe_create_points_payment_intent', methods: ['POST'])]
    public function createPointsPaymentIntent(Request $request, StripeService $stripeService): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié.'], 401);
        }

        $data = json_decode($request->getContent() ?: '', true);
        $points = (int) ($data['points'] ?? 0);
        $amount = (float) ($data['amount'] ?? 0);

        // Validate points and amount
        $validPackages = [
            100 => 3.00,
            250 => 7.00,
            500 => 12.00,
            1000 => 22.00,
            2500 => 50.00,
            5000 => 90.00,
        ];

        if (!isset($validPackages[$points]) || $validPackages[$points] !== $amount) {
            return $this->json(['message' => 'Package de points invalide.'], 400);
        }

        try {
            $result = $stripeService->createPointsPaymentIntent($points, $amount);
            $result['publishable_key'] = $stripeService->getPublishableKey();
            return $this->json($result);
        } catch (\Throwable $e) {
            return $this->json(['message' => 'Erreur lors de la création du paiement: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/api/stripe/confirm-points-payment', name: 'api_stripe_confirm_points_payment', methods: ['POST'])]
    public function confirmPointsPayment(Request $request, StripeService $stripeService): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié.'], 401);
        }

        $data = json_decode($request->getContent() ?: '', true);
        $paymentIntentId = (string) ($data['payment_intent_id'] ?? '');

        if (empty($paymentIntentId)) {
            return $this->json(['message' => 'ID de paiement manquant.'], 400);
        }

        try {
            $result = $stripeService->confirmPaymentAndCreditPoints($paymentIntentId, $user);
            return $this->json($result, $result['success'] ? 200 : 400);
        } catch (\Throwable $e) {
            return $this->json(['message' => 'Erreur lors de la confirmation: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/api/transactions/qr-code', name: 'api_transactions_qrcode', methods: ['POST'])]
    public function generateTransactionQrCode(
        Request $request, 
        EntityManagerInterface $em, 
        QRCodeService $qrCodeService
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié.'], 401);
        }

        $data = json_decode($request->getContent() ?: '', true);
        $period = (string) ($data['period'] ?? 'month');

        if (!in_array($period, ['day', 'month', '3months', 'year'])) {
            return $this->json(['message' => 'Période invalide. Utilisez: day, month, 3months, year.'], 400);
        }

        $transactionRepo = $em->getRepository(Transaction::class);
        $transactions = $transactionRepo->getTransactionsByPeriod((int) $user->getId(), $period);

        if (empty($transactions)) {
            return $this->json(['message' => 'Aucune transaction trouvée pour cette période.'], 404);
        }

        $periodLabels = [
            'day' => 'Aujourd\'hui',
            'month' => 'Ce mois',
            '3months' => '3 derniers mois',
            'year' => 'Cette année',
        ];

        $userName = $user->getFirstname() . ' ' . $user->getLastname();
        $qrCodePng = $qrCodeService->generateTransactionHistoryQrCode(
            $transactions, 
            $periodLabels[$period], 
            $userName
        );

        return new Response(
            base64_encode($qrCodePng),
            200,
            [
                'Content-Type' => 'text/plain',
                'X-Period' => $period,
                'X-Transaction-Count' => (string) count($transactions),
            ]
        );
    }

    #[Route('/api/product/{id}/purchase', name: 'api_product_purchase', methods: ['POST'])]
    public function purchaseProduct(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        ProductPurchaseService $purchaseService
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié.'], 401);
        }

        $product = $em->getRepository(Product::class)->find($id);
        if (!$product) {
            return $this->json(['message' => 'Produit non trouvé.'], 404);
        }

        $result = $purchaseService->purchaseProduct($user, $product);
        
        return $this->json($result, $result['success'] ? 200 : 400);
    }

    #[Route('/api/product/{id}/rating', name: 'api_product_rating', methods: ['POST'])]
    public function rateProduct(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        ProductRatingService $ratingService
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié.'], 401);
        }

        $product = $em->getRepository(Product::class)->find($id);
        if (!$product) {
            return $this->json(['message' => 'Produit non trouvé.'], 404);
        }

        $data = json_decode($request->getContent() ?: '', true);
        $rating = (int) ($data['rating'] ?? 0);

        $result = $ratingService->rateProduct($id, $user->getId(), $rating);
        
        return $this->json($result, $result['success'] ? 200 : 400);
    }

    #[Route('/api/product/{id}/rating', name: 'api_product_get_rating', methods: ['GET'])]
    public function getProductRating(
        int $id,
        EntityManagerInterface $em,
        ProductRatingService $ratingService
    ): JsonResponse {
        $product = $em->getRepository(Product::class)->find($id);
        if (!$product) {
            return $this->json(['message' => 'Produit non trouvé.'], 404);
        }

        $user = $this->getUser();
        $userRating = null;
        if ($user instanceof User) {
            $userRating = $ratingService->getUserRating($id, $user->getId());
        }

        $stats = $ratingService->getProductRatingStats($id);

        return $this->json([
            'average' => $stats['average'],
            'total' => $stats['total'],
            'userRating' => $userRating,
        ]);
    }

    #[Route('/api/ad/{id}/rating', name: 'api_ad_rating', methods: ['POST'])]
    public function rateAd(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        AdRatingService $ratingService
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié.'], 401);
        }

        $ad = $em->getRepository(Ad::class)->find($id);
        if (!$ad) {
            return $this->json(['message' => 'Annonce non trouvée.'], 404);
        }

        $data = json_decode($request->getContent() ?: '', true);
        $rating = (int) ($data['rating'] ?? 0);

        $result = $ratingService->rateAd($id, $user->getId(), $rating);
        
        return $this->json($result, $result['success'] ? 200 : 400);
    }

    #[Route('/api/ad/{id}/rating', name: 'api_ad_get_rating', methods: ['GET'])]
    public function getAdRating(
        int $id,
        EntityManagerInterface $em,
        AdRatingService $ratingService
    ): JsonResponse {
        $ad = $em->getRepository(Ad::class)->find($id);
        if (!$ad) {
            return $this->json(['message' => 'Annonce non trouvée.'], 404);
        }

        $user = $this->getUser();
        $userRating = null;
        if ($user instanceof User) {
            $userRating = $ratingService->getUserRating($id, $user->getId());
        }

        $stats = $ratingService->getAdRatingStats($id);

        return $this->json([
            'average' => $stats['average'],
            'total' => $stats['total'],
            'userRating' => $userRating,
        ]);
    }

    // ==================== PRODUCT FAVORITES ====================

    #[Route('/api/product/{id}/favorite', name: 'api_product_favorite', methods: ['POST'])]
    public function toggleProductFavorite(
        int $id,
        EntityManagerInterface $em,
        ProductFavoriteService $favoriteService
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié.'], 401);
        }

        $product = $em->getRepository(Product::class)->find($id);
        if (!$product) {
            return $this->json(['message' => 'Produit non trouvé.'], 404);
        }

        $result = $favoriteService->toggleFavorite($id, $user->getId());
        
        return $this->json($result);
    }

    #[Route('/api/product/{id}/favorite', name: 'api_product_get_favorite', methods: ['GET'])]
    public function getProductFavorite(
        int $id,
        EntityManagerInterface $em,
        ProductFavoriteService $favoriteService
    ): JsonResponse {
        $product = $em->getRepository(Product::class)->find($id);
        if (!$product) {
            return $this->json(['message' => 'Produit non trouvé.'], 404);
        }

        $user = $this->getUser();
        $isFavorite = false;
        if ($user instanceof User) {
            $isFavorite = $favoriteService->isFavorite($id, $user->getId());
        }

        return $this->json([
            'isFavorite' => $isFavorite,
            'count' => $favoriteService->getProductFavoriteCount($id),
        ]);
    }

    // ==================== CART ====================

    #[Route('/api/cart', name: 'api_cart_get', methods: ['GET'])]
    public function getCart(CartService $cartService): JsonResponse
    {
        $items = $cartService->getCartItems();
        $data = [];

        foreach ($items as $item) {
            $product = $item['product'];
            $data[] = [
                'productId' => $product->getId(),
                'name' => $product->getName(),
                'pricePoints' => $product->getPricePoints(),
                'quantity' => $item['quantity'],
                'subtotal' => $item['subtotal'],
                'image' => $product->getImage(),
            ];
        }

        return $this->json([
            'items' => $data,
            'totalPoints' => $cartService->getTotalPoints(),
            'totalItems' => $cartService->getTotalItems(),
        ]);
    }

    #[Route('/api/cart/add/{id}', name: 'api_cart_add', methods: ['POST'])]
    public function addToCart(int $id, Request $request, CartService $cartService): JsonResponse
    {
        $quantity = (int) $request->request->get('quantity', 1);
        $result = $cartService->add($id, $quantity);

        return $this->json($result);
    }

    #[Route('/api/cart/remove/{id}', name: 'api_cart_remove', methods: ['POST'])]
    public function removeFromCart(int $id, CartService $cartService): JsonResponse
    {
        $result = $cartService->remove($id);
        return $this->json($result);
    }

    #[Route('/api/cart/update/{id}', name: 'api_cart_update', methods: ['POST'])]
    public function updateCart(int $id, Request $request, CartService $cartService): JsonResponse
    {
        $quantity = (int) $request->request->get('quantity', 1);
        $result = $cartService->update($id, $quantity);
        return $this->json($result);
    }

    #[Route('/api/cart/clear', name: 'api_cart_clear', methods: ['POST'])]
    public function clearCart(CartService $cartService): JsonResponse
    {
        $cartService->clear();
        return $this->json(['success' => true, 'message' => 'Panier vidé.']);
    }

    #[Route('/user/cart', name: 'front_cart', methods: ['GET'])]
    public function viewCart(CartService $cartService): Response
    {
        return $this->render('front/cart/index.html.twig', [
            'items' => $cartService->getCartItems(),
            'totalPoints' => $cartService->getTotalPoints(),
            'totalItems' => $cartService->getTotalItems(),
        ]);
    }

    #[Route('/user/cart/checkout', name: 'front_cart_checkout', methods: ['POST'])]
    public function checkoutCart(CartService $cartService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['success' => false, 'message' => 'Non authentifié.'], 401);
        }

        $result = $cartService->checkout($user);

        // If checkout successful, return PDF directly
        if ($result['success'] && isset($result['pdf'])) {
            $orderNumber = 'FNV-' . strtoupper(uniqid());
            $filename = 'ticket-' . $orderNumber . '.pdf';

            return new \Symfony\Component\HttpFoundation\Response(
                $result['pdf'],
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                ]
            );
        }

        return $this->json($result);
    }
}
