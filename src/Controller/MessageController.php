<?php

namespace App\Controller;

use App\Entity\Message;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/message')]
final class MessageController extends AbstractController
{
    #[Route(name: 'app_message_index', methods: ['GET'])]
    public function index(Request $request, MessageRepository $messageRepository): Response
    {
        $search     = $request->query->get('search', '');
        $senderRole = $request->query->get('senderRole', '');
        $sortBy     = $request->query->get('sortBy', 'sentAt');
        $sortDir    = $request->query->get('sortDir', 'DESC');

        $messages = $messageRepository->searchFilterSort($search, $senderRole, $sortBy, $sortDir);

        return $this->render('message/index.html.twig', [
            'messages'   => $messages,
            'search'     => $search,
            'senderRole' => $senderRole,
            'sortBy'     => $sortBy,
            'sortDir'    => $sortDir,
        ]);
    }

    #[Route('/new', name: 'app_message_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $content    = $request->request->get('content');
            $senderRole = $request->request->get('senderRole');
            $idTicket   = $request->request->get('idTicket');

            if ($content && $senderRole && $idTicket) {
                $message = new Message();
                $message->setContent($content);
                $message->setSenderRole($senderRole);
                $message->setIdTicket((int)$idTicket);
                $message->setSentAt(new \DateTime());

                $entityManager->persist($message);
                $entityManager->flush();

                $this->addFlash('success', 'Message envoyé avec succès');
                return $this->redirectToRoute('app_message_index');
            }

            $this->addFlash('error', 'Tous les champs sont obligatoires');
        }

        return $this->render('message/new.html.twig');
    }

    #[Route('/{id}', name: 'app_message_show', methods: ['GET'])]
    public function show(Message $message): Response
    {
        return $this->render('message/show.html.twig', [
            'message' => $message,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_message_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Message $message, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $content    = $request->request->get('content');
            $senderRole = $request->request->get('senderRole');
            $idTicket   = $request->request->get('idTicket');

            if ($content && $senderRole && $idTicket) {
                $message->setContent($content);
                $message->setSenderRole($senderRole);
                $message->setIdTicket((int)$idTicket);
                // On ne modifie pas la date d'envoi originale
                $entityManager->flush();

                $this->addFlash('success', 'Message modifié avec succès');
                return $this->redirectToRoute('app_message_index');
            }

            $this->addFlash('error', 'Tous les champs sont obligatoires');
        }

        return $this->render('message/edit.html.twig', [
            'message' => $message,
        ]);
    }

    #[Route('/{id}', name: 'app_message_delete', methods: ['POST'])]
    public function delete(Request $request, Message $message, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$message->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($message);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_message_index', [], Response::HTTP_SEE_OTHER);
    }
}