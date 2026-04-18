<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class ProductPurchaseService
{
    public function __construct(
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $mailerFromEmail = 'aziz.fafi@gmail.com',
        private string $mailerFromName = 'Finovate',
    ) {}

    /**
     * Purchase a product - debit points and decrease stock
     */
    public function purchaseProduct(User $user, Product $product): array
    {
        // Check stock
        if ($product->getStock() <= 0) {
            return [
                'success' => false,
                'message' => 'Ce produit est en rupture de stock.',
            ];
        }

        // Check user has enough points
        $userPoints = $user->getPoints();
        $pricePoints = $product->getPricePoints();

        if ($userPoints < $pricePoints) {
            return [
                'success' => false,
                'message' => 'Vous n\'avez pas assez de points. Points nécessaires: ' . $pricePoints . ', Points disponibles: ' . $userPoints,
            ];
        }

        // Debit points from user
        $newPoints = $userPoints - $pricePoints;
        $user->setPoints($newPoints);

        // Decrease stock
        $product->setStock($product->getStock() - 1);

        // Persist changes
        $this->em->flush();

        // Send confirmation email
        $this->sendPurchaseConfirmationEmail($user, $product, $pricePoints);

        return [
            'success' => true,
            'message' => 'Achat réussi ! Vous avez reçu: ' . $product->getName(),
            'product_name' => $product->getName(),
            'points_spent' => $pricePoints,
            'new_points' => $newPoints,
            'remaining_stock' => $product->getStock(),
        ];
    }

    /**
     * Send purchase confirmation email
     */
    private function sendPurchaseConfirmationEmail(User $user, Product $product, int $pointsSpent): void
    {
        $purchaseDate = new \DateTime();
        $formattedDate = $purchaseDate->format('d/m/Y à H:i');

        $html = $this->buildPurchaseEmailHtml($user, $product, $pointsSpent, $formattedDate);

        $this->sendEmail(
            $user->getEmail(),
            $user->getFirstname(),
            '[FINOVATE] Confirmation de votre achat',
            $html
        );
    }

    private function sendEmail(string $to, string $name, string $subject, string $html): void
    {
        try {
            $email = (new Email())
                ->from($this->mailerFromName . ' <' . $this->mailerFromEmail . '>')
                ->to($to)
                ->subject($subject)
                ->html($html);

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->warning('ProductPurchaseService email failed: ' . $e->getMessage());
        }
    }

    private function buildPurchaseEmailHtml(User $user, Product $product, int $pointsSpent, string $date): string
    {
        $productName = htmlspecialchars($product->getName());
        $productDesc = htmlspecialchars($product->getDescription() ?? 'Aucune description');
        $userName = htmlspecialchars($user->getFirstname() . ' ' . $user->getLastname());

        return "
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #0f766e 0%, #10b981 100%); padding: 32px 24px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: 800; }
        .header p { color: rgba(255,255,255,0.9); margin: 8px 0 0; font-size: 14px; }
        .content { padding: 32px 24px; }
        .greeting { font-size: 16px; color: #374151; margin-bottom: 24px; }
        .product-box { background: #f9fafb; border-radius: 12px; padding: 20px; margin-bottom: 24px; border: 1px solid #e5e7eb; }
        .product-box h3 { color: #0f766e; margin: 0 0 12px; font-size: 18px; font-weight: 800; }
        .product-box p { color: #6b7280; margin: 0; font-size: 14px; line-height: 1.6; }
        .details-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .details-table tr { border-bottom: 1px solid #e5e7eb; }
        .details-table td { padding: 12px 0; font-size: 14px; }
        .details-table td:first-child { color: #6b7280; font-weight: 600; }
        .details-table td:last-child { color: #111827; font-weight: 800; text-align: right; }
        .points-badge { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); color: #ffffff; padding: 4px 12px; border-radius: 20px; font-weight: 800; }
        .success-icon { width: 60px; height: 60px; background: rgba(16, 185, 129, 0.15); border-radius: 50%; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; }
        .footer { background: #f9fafb; padding: 20px 24px; text-align: center; border-top: 1px solid #e5e7eb; }
        .footer p { color: #9ca3af; font-size: 12px; margin: 0; }
        .footer a { color: #0f766e; text-decoration: none; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>&#10003; Achat Confirmé</h1>
            <p>Merci pour votre confiance !</p>
        </div>
        <div class='content'>
            <p class='greeting'>Bonjour <strong>{$userName}</strong>,</p>
            <p style='color: #374151; margin-bottom: 24px;'>Nous vous confirmons l'achat de votre produit. Voici les détails de votre transaction :</p>
            
            <div class='product-box'>
                <h3>&#128230; {$productName}</h3>
                <p>{$productDesc}</p>
            </div>

            <table class='details-table'>
                <tr>
                    <td>Date d'achat</td>
                    <td>{$date}</td>
                </tr>
                <tr>
                    <td>Produit</td>
                    <td>{$productName}</td>
                </tr>
                <tr>
                    <td>Points dépensés</td>
                    <td><span class='points-badge'>{$pointsSpent} pts</span></td>
                </tr>
                <tr>
                    <td>Nouveau solde de points</td>
                    <td>{$user->getPoints()} pts</td>
                </tr>
            </table>

            <div style='background: rgba(15, 118, 110, 0.08); border-radius: 12px; padding: 16px; border-left: 4px solid #0f766e;'>
                <p style='margin: 0; color: #374151; font-size: 14px;'>
                    <strong>&#128161; Astuce :</strong> Continuez à regarder des publicités ou achetez des points pour obtenir plus de produits exclusifs !
                </p>
            </div>
        </div>
        <div class='footer'>
            <p>&copy; 2024 FINOVATE - Votre banque digitale</p>
            <p style='margin-top: 8px;'><a href='https://finovate.com'>www.finovate.com</a></p>
        </div>
    </div>
</body>
</html>";
    }
}
