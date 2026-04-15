<?php

namespace App\Service;

class QRCodeService
{
    public function generateTransactionHistoryQrCode(array $transactions, string $period, string $userName): string
    {
        $data = $this->formatTransactionData($transactions, $period, $userName);

        // Utiliser l'API externe goqr.me pour générer le QR code (pas besoin de GD)
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=' . urlencode($data);
        
        $qrImage = @file_get_contents($qrUrl);
        
        if ($qrImage === false) {
            throw new \RuntimeException('Impossible de générer le QR code. Vérifiez votre connexion internet.');
        }

        return $qrImage;
    }

    private function formatTransactionData(array $transactions, string $period, string $userName): string
    {
        $lines = [];
        $lines[] = "=== HISTORIQUE TRANSACTIONS FINOVATE ===";
        $lines[] = "Utilisateur: $userName";
        $lines[] = "Periode: $period";
        $lines[] = "Genere le: " . date('d/m/Y H:i');
        $lines[] = "";
        
        $totalSent = 0;
        $totalReceived = 0;
        
        foreach ($transactions as $tx) {
            $amount = (float) $tx['amount'];
            $date = $tx['date'];
            $type = $tx['direction']; // 'sent' or 'received'
            
            if ($type === 'sent') {
                $totalSent += $amount;
                $lines[] = "[-] $date | $amount TND | " . ($tx['description'] ?? 'Virement');
            } else {
                $totalReceived += $amount;
                $lines[] = "[+] $date | $amount TND | " . ($tx['description'] ?? 'Reception');
            }
        }
        
        $lines[] = "";
        $lines[] = "=== RESUME ===";
        $lines[] = "Total envoye: " . number_format($totalSent, 2, ',', ' ') . " TND";
        $lines[] = "Total recu: " . number_format($totalReceived, 2, ',', ' ') . " TND";
        $lines[] = "Nombre de transactions: " . count($transactions);
        
        return implode("\n", $lines);
    }
}
