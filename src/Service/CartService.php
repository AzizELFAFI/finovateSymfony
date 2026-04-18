<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class CartService
{
    private const SESSION_KEY = 'cart';

    public function __construct(
        private RequestStack $requestStack,
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private PdfGeneratorService $pdfGenerator,
        private string $mailerFromEmail = 'aziz.fafi@gmail.com',
        private string $mailerFromName = 'Finovate'
    ) {}

    private function getSession()
    {
        return $this->requestStack->getSession();
    }

    private function getCart(): array
    {
        return $this->getSession()->get(self::SESSION_KEY, []);
    }

    private function saveCart(array $cart): void
    {
        $this->getSession()->set(self::SESSION_KEY, $cart);
    }

    public function add(int $productId, int $quantity = 1): array
    {
        $product = $this->em->getRepository(Product::class)->find($productId);
        if (!$product) {
            return ['success' => false, 'message' => 'Produit non trouvé.'];
        }

        if ($product->getStock() < $quantity) {
            return ['success' => false, 'message' => 'Stock insuffisant.'];
        }

        $cart = $this->getCart();

        if (isset($cart[$productId])) {
            $newQuantity = $cart[$productId]['quantity'] + $quantity;
            if ($product->getStock() < $newQuantity) {
                return ['success' => false, 'message' => 'Stock insuffisant pour cette quantité.'];
            }
            $cart[$productId]['quantity'] = $newQuantity;
        } else {
            $cart[$productId] = [
                'quantity' => $quantity,
            ];
        }

        $this->saveCart($cart);

        return [
            'success' => true,
            'message' => 'Produit ajouté au panier.',
            'cartCount' => $this->getTotalItems(),
        ];
    }

    public function remove(int $productId): array
    {
        $cart = $this->getCart();

        if (isset($cart[$productId])) {
            unset($cart[$productId]);
            $this->saveCart($cart);
        }

        return [
            'success' => true,
            'message' => 'Produit retiré du panier.',
            'cartCount' => $this->getTotalItems(),
        ];
    }

    public function update(int $productId, int $quantity): array
    {
        $product = $this->em->getRepository(Product::class)->find($productId);
        if (!$product) {
            return ['success' => false, 'message' => 'Produit non trouvé.'];
        }

        if ($quantity <= 0) {
            return $this->remove($productId);
        }

        if ($product->getStock() < $quantity) {
            return ['success' => false, 'message' => 'Stock insuffisant.'];
        }

        $cart = $this->getCart();

        if (isset($cart[$productId])) {
            $cart[$productId]['quantity'] = $quantity;
            $this->saveCart($cart);
        }

        return [
            'success' => true,
            'message' => 'Quantité mise à jour.',
            'cartCount' => $this->getTotalItems(),
        ];
    }

    public function clear(): void
    {
        $this->saveCart([]);
    }

    public function getCartItems(): array
    {
        $cart = $this->getCart();
        $items = [];

        foreach ($cart as $productId => $data) {
            $product = $this->em->getRepository(Product::class)->find($productId);
            if ($product) {
                $items[] = [
                    'product' => $product,
                    'quantity' => $data['quantity'],
                    'subtotal' => $product->getPricePoints() * $data['quantity'],
                ];
            }
        }

        return $items;
    }

    public function getTotalPoints(): int
    {
        $total = 0;
        foreach ($this->getCartItems() as $item) {
            $total += $item['subtotal'];
        }
        return $total;
    }

    public function getTotalItems(): int
    {
        $count = 0;
        foreach ($this->getCart() as $data) {
            $count += $data['quantity'];
        }
        return $count;
    }

    public function hasProduct(int $productId): bool
    {
        $cart = $this->getCart();
        return isset($cart[$productId]);
    }

    public function getProductQuantity(int $productId): int
    {
        $cart = $this->getCart();
        return $cart[$productId]['quantity'] ?? 0;
    }

    /**
     * Validate and process the cart checkout
     */
    public function checkout(User $user): array
    {
        $items = $this->getCartItems();

        if (empty($items)) {
            return ['success' => false, 'message' => 'Le panier est vide.'];
        }

        $totalPoints = $this->getTotalPoints();
        $userPoints = $user->getPoints();

        // Check if user has enough points
        if ($userPoints < $totalPoints) {
            return [
                'success' => false,
                'message' => 'Points insuffisants. Vous avez ' . $userPoints . ' pts, nécessaire: ' . $totalPoints . ' pts.',
            ];
        }

        // Verify stock for all products
        foreach ($items as $item) {
            $product = $item['product'];
            $quantity = $item['quantity'];

            if ($product->getStock() < $quantity) {
                return [
                    'success' => false,
                    'message' => 'Stock insuffisant pour "' . $product->getName() . '". Stock disponible: ' . $product->getStock(),
                ];
            }
        }

        // Start transaction
        $this->em->beginTransaction();

        try {
            // Debit points from user
            $user->setPoints($userPoints - $totalPoints);

            // Update stock for each product
            foreach ($items as $item) {
                $product = $item['product'];
                $quantity = $item['quantity'];
                $product->setStock($product->getStock() - $quantity);
            }

            // Commit transaction
            $this->em->flush();
            $this->em->commit();

            // Clear cart
            $this->clear();

            // Generate PDF ticket
            $orderDate = new \DateTime();
            $pdfContent = $this->pdfGenerator->generateOrderTicket($user, $items, $totalPoints, $orderDate);

            // Send confirmation email
            $this->sendOrderConfirmationEmail($user, $items, $totalPoints);

            return [
                'success' => true,
                'message' => 'Commande validée avec succès !',
                'totalPoints' => $totalPoints,
                'newPoints' => $user->getPoints(),
                'itemsCount' => count($items),
                'pdf' => $pdfContent,
                'orderDate' => $orderDate,
            ];

        } catch (\Throwable $e) {
            $this->em->rollback();
            return [
                'success' => false,
                'message' => 'Erreur lors de la validation: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Send order confirmation email
     */
    private function sendOrderConfirmationEmail(User $user, array $items, int $totalPoints): void
    {
        $orderDate = new \DateTime();
        $formattedDate = $orderDate->format('d/m/Y à H:i');

        $html = $this->buildOrderEmailHtml($user, $items, $totalPoints, $formattedDate);

        try {
            $email = (new Email())
                ->from($this->mailerFromName . ' <' . $this->mailerFromEmail . '>')
                ->to($user->getEmail())
                ->subject('[FINOVATE] Confirmation de votre commande')
                ->html($html);

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->warning('CartService email failed: ' . $e->getMessage());
        }
    }

    private function buildOrderEmailHtml(User $user, array $items, int $totalPoints, string $date): string
    {
        $userName = htmlspecialchars($user->getFirstname() . ' ' . $user->getLastname());
        $newPoints = $user->getPoints();

        // Build items list
        $itemsHtml = '';
        foreach ($items as $item) {
            $productName = htmlspecialchars($item['product']->getName());
            $quantity = $item['quantity'];
            $subtotal = $item['subtotal'];
            $itemsHtml .= "
                <tr>
                    <td style='padding:10px;border-bottom:1px solid #e5e7eb;'>{$productName}</td>
                    <td style='padding:10px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$quantity}</td>
                    <td style='padding:10px;border-bottom:1px solid #e5e7eb;text-align:right;'><span style='background:linear-gradient(135deg,#f59e0b,#fbbf24);color:#fff;padding:4px 10px;border-radius:12px;font-weight:800;'>{$subtotal} pts</span></td>
                </tr>";
        }

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
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th { background: #f9fafb; padding: 12px 10px; text-align: left; font-weight: 800; color: #0f766e; border-bottom: 2px solid #e5e7eb; }
        th:last-child { text-align: right; }
        th:nth-child(2) { text-align: center; }
        .total-row { background: rgba(15,118,110,0.06); font-weight: 800; }
        .total-row td { padding: 16px 10px; font-size: 16px; }
        .points-badge { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); color: #ffffff; padding: 6px 16px; border-radius: 20px; font-weight: 800; }
        .footer { background: #f9fafb; padding: 20px 24px; text-align: center; border-top: 1px solid #e5e7eb; }
        .footer p { color: #9ca3af; font-size: 12px; margin: 0; }
        .footer a { color: #0f766e; text-decoration: none; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>&#10003; Commande Confirmée</h1>
            <p>Merci pour votre achat !</p>
        </div>
        <div class='content'>
            <p class='greeting'>Bonjour <strong>{$userName}</strong>,</p>
            <p style='color: #374151; margin-bottom: 24px;'>Nous vous confirmons la validation de votre commande. Voici le récapitulatif :</p>
            
            <table>
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Quantité</th>
                        <th>Sous-total</th>
                    </tr>
                </thead>
                <tbody>
                    {$itemsHtml}
                    <tr class='total-row'>
                        <td colspan='2' style='padding:16px 10px;font-size:16px;'>Total</td>
                        <td style='padding:16px 10px;text-align:right;font-size:16px;'><span class='points-badge'>{$totalPoints} pts</span></td>
                    </tr>
                </tbody>
            </table>

            <table style='width:100%;margin-bottom:24px;'>
                <tr>
                    <td style='padding:8px 0;color:#6b7280;font-weight:600;'>Date de commande</td>
                    <td style='padding:8px 0;text-align:right;font-weight:800;'>{$date}</td>
                </tr>
                <tr>
                    <td style='padding:8px 0;color:#6b7280;font-weight:600;'>Nouveau solde</td>
                    <td style='padding:8px 0;text-align:right;font-weight:800;'>{$newPoints} pts</td>
                </tr>
            </table>

            <div style='background: rgba(15, 118, 110, 0.08); border-radius: 12px; padding: 16px; border-left: 4px solid #0f766e;'>
                <p style='margin: 0; color: #374151; font-size: 14px;'>
                    <strong>&#128161; Astuce :</strong> Continuez à accumuler des points pour découvrir plus de produits exclusifs !
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
