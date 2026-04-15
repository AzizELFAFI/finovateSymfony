<?php

namespace App\Service;

use Stripe\StripeClient;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class StripeService
{
    public function __construct(
        private StripeClient $stripe,
        private EntityManagerInterface $em
    ) {}

    /**
     * Create a PaymentIntent for card top-up
     */
    public function createPaymentIntent(float $amount): array
    {
        // Use EUR for Stripe (TND not supported), amount in cents
        $amountInCents = (int) ($amount * 100);

        $paymentIntent = $this->stripe->paymentIntents->create([
            'amount' => $amountInCents,
            'currency' => 'eur',
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
            'metadata' => [
                'type' => 'card_topup',
            ],
        ]);

        return [
            'client_secret' => $paymentIntent->client_secret,
            'payment_intent_id' => $paymentIntent->id,
            'amount' => $amount,
        ];
    }

    /**
     * Confirm a payment and credit user balance
     */
    public function confirmPaymentAndCredit(string $paymentIntentId, User $user): array
    {
        $paymentIntent = $this->stripe->paymentIntents->retrieve($paymentIntentId);

        if ($paymentIntent->status !== 'succeeded') {
            return [
                'success' => false,
                'message' => 'Le paiement n\'a pas été complété.',
            ];
        }

        // Get amount from payment intent (in cents)
        $amountInCents = $paymentIntent->amount;
        $amount = $amountInCents / 100;

        // Credit user balance
        $currentBalance = (float) str_replace(',', '.', (string) $user->getSolde());
        $newBalance = $currentBalance + $amount;
        $user->setSolde((string) $newBalance);

        $this->em->flush();

        return [
            'success' => true,
            'message' => 'Votre carte a été alimentée avec succès.',
            'amount' => $amount,
            'new_balance' => $newBalance,
        ];
    }

    /**
     * Get Stripe publishable key for frontend
     */
    public function getPublishableKey(): string
    {
        return $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? '';
    }
}
