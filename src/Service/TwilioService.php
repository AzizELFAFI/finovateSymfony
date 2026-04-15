<?php

namespace App\Service;

use Twilio\Rest\Client;

class TwilioService
{
    public function sendSms(string $to, string $message): void
    {
        $sid = $_ENV['TWILIO_ACCOUNT_SID'] ?? '';
        $token = $_ENV['TWILIO_AUTH_TOKEN'] ?? '';
        $from = $_ENV['TWILIO_FROM_NUMBER'] ?? '';

        if ($sid === '' || $token === '' || $from === '') {
            throw new \RuntimeException('Twilio configuration is missing.');
        }

        $client = new Client($sid, $token);
        $client->messages->create($to, [
            'from' => $from,
            'body' => $message,
        ]);
    }

    public function formatTunisiaNumber(int|string $phoneNumber): string
    {
        $raw = preg_replace('/\D+/', '', (string) $phoneNumber);

        if ($raw === '') {
            throw new \InvalidArgumentException('Phone number is empty.');
        }

        if (str_starts_with($raw, '216')) {
            $raw = substr($raw, 3);
        }

        if (strlen($raw) !== 8) {
            throw new \InvalidArgumentException('Invalid Tunisia phone number.');
        }

        return '+216' . $raw;
    }
}
