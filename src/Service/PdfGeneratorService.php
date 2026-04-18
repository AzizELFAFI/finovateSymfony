<?php

namespace App\Service;

use App\Entity\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class PdfGeneratorService
{
    public function __construct(
        private Environment $twig
    ) {}

    /**
     * Generate a PDF ticket for an order
     */
    public function generateOrderTicket(User $user, array $items, int $totalPoints, \DateTime $orderDate): string
    {
        $orderNumber = 'FNV-' . strtoupper(uniqid());
        
        $html = $this->twig->render('pdf/order_ticket.html.twig', [
            'user' => $user,
            'items' => $items,
            'totalPoints' => $totalPoints,
            'orderDate' => $orderDate,
            'orderNumber' => $orderNumber,
            'newPoints' => $user->getPoints(),
        ]);

        $dompdf = $this->createDompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Generate a PDF for a single product purchase
     */
    public function generateProductTicket(User $user, string $productName, int $quantity, int $pricePoints, \DateTime $purchaseDate): string
    {
        $orderNumber = 'FNV-' . strtoupper(uniqid());
        
        $html = $this->twig->render('pdf/product_ticket.html.twig', [
            'user' => $user,
            'productName' => $productName,
            'quantity' => $quantity,
            'pricePoints' => $pricePoints,
            'totalPoints' => $pricePoints * $quantity,
            'purchaseDate' => $purchaseDate,
            'orderNumber' => $orderNumber,
            'newPoints' => $user->getPoints(),
        ]);

        $dompdf = $this->createDompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function createDompdf(): Dompdf
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isFontSubsettingEnabled', true);
        
        return new Dompdf($options);
    }
}
