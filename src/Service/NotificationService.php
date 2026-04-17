<?php

namespace App\Service;

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * Web Push notification service using VAPID.
 * Subscriptions are stored in a JSON file (simple, no DB needed).
 */
class NotificationService
{
    private string $storageFile;
    private WebPush $webPush;

    public function __construct(
        private string $vapidPublicKey,
        private string $vapidPrivateKey,
        private string $vapidSubject,
        string $projectDir,
    ) {
        $this->storageFile = $projectDir . '/var/push_subscriptions.json';
        
        // Initialize WebPush with VAPID keys
        $this->webPush = new WebPush([
            'VAPID' => [
                'subject' => $this->vapidSubject,
                'publicKey' => $this->vapidPublicKey,
                'privateKey' => $this->vapidPrivateKey,
            ],
        ]);
    }

    public function getPublicKey(): string
    {
        return $this->vapidPublicKey;
    }

    public function saveSubscription(array $subscription): void
    {
        $subs = $this->loadSubscriptions();
        // Avoid duplicates by endpoint
        foreach ($subs as $s) {
            if ($s['endpoint'] === $subscription['endpoint']) return;
        }
        $subs[] = $subscription;
        file_put_contents($this->storageFile, json_encode($subs));
    }

    public function removeSubscription(string $endpoint): void
    {
        $subs = $this->loadSubscriptions();
        $subs = array_values(array_filter($subs, fn($s) => $s['endpoint'] !== $endpoint));
        file_put_contents($this->storageFile, json_encode($subs));
    }

    public function sendToAll(string $title, string $body, string $url = '/'): int
    {
        $subs = $this->loadSubscriptions();
        $sent = 0;
        foreach ($subs as $sub) {
            if ($this->send($sub, $title, $body, $url)) $sent++;
        }
        return $sent;
    }

    public function send(array $subscription, string $title, string $body, string $url = '/'): bool
    {
        $payload = json_encode([
            'title' => $title,
            'body'  => $body,
            'url'   => $url,
            'icon'  => '/backoffice/assets/images/logo-icon.svg',
        ]);

        try {
            // Create Subscription object from array
            $sub = Subscription::create($subscription);
            
            // Send notification using WebPush library
            $report = $this->webPush->sendOneNotification($sub, $payload);
            
            // Check if successful
            $success = $report->isSuccess();
            
            if (!$success) {
                error_log('Push notification failed: ' . $report->getReason());
            }
            
            return $success;
        } catch (\Throwable $e) {
            // Log error for debugging
            error_log('Push notification error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }

    private function loadSubscriptions(): array
    {
        if (!file_exists($this->storageFile)) return [];
        $data = json_decode(file_get_contents($this->storageFile), true);
        return is_array($data) ? $data : [];
    }
}