<?php

namespace App\Controller;

use App\Entity\Ticket;
use App\Service\AiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class TicketChatbotController extends AbstractController
{
    #[Route('/api/tickets/{id}/chatbot', name: 'api_ticket_chatbot', methods: ['POST'])]
    public function reply(
        Request $request,
        Ticket $ticket,
        AiService $aiService,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            return $this->json(['message' => 'Non authentifié.'], 401);
        }

        $isAdmin = $this->isGranted('ROLE_ADMIN');
        if (!$isAdmin && $ticket->getUser() !== $user) {
            return $this->json(['message' => 'Accès refusé.'], 403);
        }

        $payload = json_decode($request->getContent() ?: '', true);
        if (!is_array($payload)) {
            return $this->json(['message' => 'Payload JSON invalide.'], 400);
        }

        $prompt = trim((string) ($payload['prompt'] ?? ''));
        if ($prompt === '') {
            return $this->json(['message' => 'Le prompt est requis.'], 422);
        }

        // Build compact conversation history from the ticket context
        $history = [];
        $history[] = [
            'role' => 'user',
            'content' => 'Contexte ticket: Sujet=' . ($ticket->getType() ?? 'N/A') .
                '; Description=' . ($ticket->getDescription() ?? ''),
        ];

        $messages = $ticket->getMessages()->toArray();
        $messages = array_slice($messages, -8); // keep token usage low
        foreach ($messages as $msg) {
            if (!method_exists($msg, 'getContent') || !method_exists($msg, 'getSenderRole')) {
                continue;
            }
            $history[] = [
                'role' => $msg->getSenderRole() === 'ADMIN' ? 'assistant' : 'user',
                'content' => (string) $msg->getContent(),
            ];
        }

        $persona = $isAdmin
            ? "Tu aides un agent support FINOVATE à répondre clairement, professionnellement et brièvement. Réponds en français. Propose une réponse prête à envoyer, sans markdown."
            : "Tu aides un client FINOVATE à formuler une réponse claire et polie au support. Réponds en français. Propose une réponse prête à envoyer, sans markdown.";

        try {
            $reply = $aiService->chat($persona . "\nDemande: " . $prompt, $history);
        } catch (\Throwable $e) {
            $msg = 'Assistant IA indisponible.';
            if ($this->getParameter('kernel.environment') === 'dev') {
                $msg = $e->getMessage();
            }
            return $this->json(['message' => $msg], 502);
        }

        if (trim($reply) === '') {
            return $this->json(['message' => 'Réponse IA vide.'], 502);
        }

        return $this->json([
            'reply' => trim($reply),
            'ticketId' => $ticket->getId(),
        ]);
    }
}

