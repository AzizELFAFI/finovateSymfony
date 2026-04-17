<?php

namespace App\Controller;

use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/notifications')]
class NotificationController extends AbstractController
{
    public function __construct(private NotificationService $notif) {}

    /** Return VAPID public key for browser subscription */
    #[Route('/vapid-public-key', name: 'notif_vapid_key', methods: ['GET'])]
    public function vapidKey(): JsonResponse
    {
        return $this->json(['publicKey' => $this->notif->getPublicKey()]);
    }

    /** Save a push subscription from the browser */
    #[Route('/subscribe', name: 'notif_subscribe', methods: ['POST'])]
    public function subscribe(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (empty($data['endpoint'])) {
            return $this->json(['error' => 'Invalid subscription'], 400);
        }
        $this->notif->saveSubscription($data);
        return $this->json(['success' => true]);
    }

    /** Unsubscribe */
    #[Route('/unsubscribe', name: 'notif_unsubscribe', methods: ['POST'])]
    public function unsubscribe(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $this->notif->removeSubscription($data['endpoint'] ?? '');
        return $this->json(['success' => true]);
    }
}