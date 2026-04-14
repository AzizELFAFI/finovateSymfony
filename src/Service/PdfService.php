<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class PdfService
{
    public function __construct(
        private Environment $twig,
    ) {}

    public function generateTransactionReceipt(array $data): string
    {
        $html = $this->twig->render('pdf/transaction_receipt.html.twig', $data);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        // Format ticket de caisse: 80mm x hauteur auto (297mm max)
        $dompdf->setPaper([0, 0, 226.77, 600], 'portrait'); // 80mm = 226.77 points
        $dompdf->render();

        return $dompdf->output();
    }
}
