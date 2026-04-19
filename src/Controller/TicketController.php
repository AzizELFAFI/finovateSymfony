<?php

namespace App\Controller;

use App\Entity\Ticket;
use App\Entity\Message;
use App\Form\TicketType;
use App\Form\MessageType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/user/tickets')]
class TicketController extends AbstractController
{
    #[Route('/', name: 'app_ticket_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('front_login');
        }

        $tickets = $entityManager
            ->getRepository(Ticket::class)
            ->findBy(['user' => $user], ['dateCreation' => 'DESC']);

        return $this->render('ticket/index.html.twig', [
            'tickets' => $tickets,
        ]);
    }

    #[Route('/new', name: 'app_ticket_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('front_login');
        }

        $ticket = new Ticket();
        $ticket->setUser($user);

        $form = $this->createForm(TicketType::class, $ticket);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($ticket);
            $entityManager->flush();

            $this->addFlash('success', 'Votre réclamation a été créée avec succès.');
            return $this->redirectToRoute('app_ticket_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('ticket/new.html.twig', [
            'ticket' => $ticket,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/messagerie/{id?}', name: 'app_ticket_messagerie', methods: ['GET', 'POST'])]
    public function messagerie(
        Request $request,
        EntityManagerInterface $entityManager,
        HubInterface $hub,
        #[Autowire(service: 'limiter.ticket_message')] RateLimiterFactory $ticketMessageLimiter,
        ?Ticket $ticket = null
    ): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('front_login');
        }

        $tickets = $entityManager
            ->getRepository(Ticket::class)
            ->findBy(['user' => $user], ['dateCreation' => 'DESC']);

        // If no specific ticket is selected, try to select the first one by default
        if (!$ticket && count($tickets) > 0) {
            $ticket = $tickets[0];
        }

        // Security check if a ticket is accessed
        if ($ticket && $ticket->getUser() !== $user) {
            throw $this->createAccessDeniedException();
        }

        $form = null;
        if ($ticket) {
            $message = new Message();
            $message->setTicket($ticket);
            $message->setSenderRole('USER');
            
            $form = $this->createForm(MessageType::class, $message);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $limiter = $ticketMessageLimiter->create((string) $user->getUserIdentifier());
                $limit = $limiter->consume(1);
                if (!$limit->isAccepted()) {
                    $this->addFlash('danger', 'Trop de messages envoyés. Veuillez patienter quelques secondes.');
                    return $this->redirectToRoute('app_ticket_messagerie', ['id' => $ticket->getId()]);
                }

                $entityManager->persist($message);
                $entityManager->flush();

                $topic = '/tickets/' . $ticket->getId();
                $hub->publish(new Update($topic, json_encode([
                    'type' => 'message',
                    'ticketId' => $ticket->getId(),
                    'senderRole' => 'USER',
                    'content' => (string) $message->getContent(),
                    'sentAt' => $message->getSentAt() ? $message->getSentAt()->format('Y-m-d H:i:s') : (new \DateTime())->format('Y-m-d H:i:s'),
                ], JSON_THROW_ON_ERROR)));

                return $this->redirectToRoute('app_ticket_messagerie', ['id' => $ticket->getId()]);
            }
            $form = $form->createView();
        }

        return $this->render('ticket/messagerie.html.twig', [
            'tickets' => $tickets,
            'active_ticket' => $ticket,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/typing', name: 'app_ticket_typing', methods: ['POST'])]
    public function typing(Ticket $ticket, EntityManagerInterface $entityManager, HubInterface $hub): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Non authentifié.'], 401);
        }

        if ($ticket->getUser() !== $user) {
            return $this->json(['message' => 'Accès refusé.'], 403);
        }

        $topic = '/tickets/' . $ticket->getId();
        $hub->publish(new Update($topic, json_encode([
            'type' => 'typing',
            'ticketId' => $ticket->getId(),
            'senderRole' => 'USER',
            'typing' => true,
            'at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], JSON_THROW_ON_ERROR)));

        return $this->json(['ok' => true]);
    }
}
