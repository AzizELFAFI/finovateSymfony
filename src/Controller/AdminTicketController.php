<?php

namespace App\Controller;

use App\Entity\Ticket;
use App\Entity\Message;
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

#[Route('/admin/reclamations')]
class AdminTicketController extends AbstractController
{
    #[Route('/{id?}', name: 'admin_reclamations_index', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        HubInterface $hub,
        #[Autowire(service: 'limiter.ticket_message')] RateLimiterFactory $ticketMessageLimiter,
        ?Ticket $ticket = null
    ): Response
    {
        // On récupère tous les tickets
        $tickets = $entityManager
            ->getRepository(Ticket::class)
            ->findBy([], ['dateCreation' => 'DESC']);

        if (!$ticket && count($tickets) > 0) {
            $ticket = $tickets[0];
        }

        $form = null;
        if ($ticket) {
            $message = new Message();
            $message->setTicket($ticket);
            $message->setSenderRole('ADMIN');
            
            $form = $this->createForm(MessageType::class, $message);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $limiter = $ticketMessageLimiter->create('admin');
                $limit = $limiter->consume(1);
                if (!$limit->isAccepted()) {
                    $this->addFlash('danger', 'Trop de messages envoyés. Veuillez patienter quelques secondes.');
                    return $this->redirectToRoute('admin_reclamations_index', ['id' => $ticket->getId()]);
                }

                // If it's a new message from admin, maybe change the ticket status to EN_COURS
                if ($ticket->getStatut() === 'NOUVEAU') {
                    $ticket->setStatut('EN_COURS');
                    $entityManager->persist($ticket);
                }
                
                $entityManager->persist($message);
                $entityManager->flush();

                $topic = '/tickets/' . $ticket->getId();
                $hub->publish(new Update($topic, json_encode([
                    'type' => 'message',
                    'ticketId' => $ticket->getId(),
                    'senderRole' => 'ADMIN',
                    'content' => (string) $message->getContent(),
                    'sentAt' => $message->getSentAt() ? $message->getSentAt()->format('Y-m-d H:i:s') : (new \DateTime())->format('Y-m-d H:i:s'),
                ], JSON_THROW_ON_ERROR)));

                return $this->redirectToRoute('admin_reclamations_index', ['id' => $ticket->getId()]);
            }
            $form = $form->createView();
        }

        return $this->render('backoffice/reclamations.html.twig', [
            'tickets' => $tickets,
            'active_ticket' => $ticket,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/close', name: 'admin_reclamations_close', methods: ['POST'])]
    public function close(Request $request, Ticket $ticket, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('close'.$ticket->getId(), $request->request->get('_token'))) {
            $ticket->setStatut('FERME');
            $ticket->setDateResolution(new \DateTime());
            $entityManager->flush();
            $this->addFlash('success', 'Le ticket a été clôturé.');
        }

        return $this->redirectToRoute('admin_reclamations_index', ['id' => $ticket->getId()]);
    }

    #[Route('/{id}/typing', name: 'admin_reclamations_typing', methods: ['POST'])]
    public function typing(Ticket $ticket, HubInterface $hub): JsonResponse
    {
        // If you want stricter checks: ensure ROLE_ADMIN
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['message' => 'Accès refusé.'], 403);
        }

        $topic = '/tickets/' . $ticket->getId();
        $hub->publish(new Update($topic, json_encode([
            'type' => 'typing',
            'ticketId' => $ticket->getId(),
            'senderRole' => 'ADMIN',
            'typing' => true,
            'at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], JSON_THROW_ON_ERROR)));

        return $this->json(['ok' => true]);
    }
}
