<?php

namespace App\Bundle\ShareBundle\Service;

/**
 * Génère les URLs de partage pour chaque réseau social.
 */
class ShareService
{
    /**
     * Retourne tous les liens de partage pour une URL et un titre donnés.
     */
    public function getShareLinks(string $url, string $title): array
    {
        $encodedUrl   = urlencode($url);
        $encodedTitle = urlencode($title . ' - FINOVATE');
        $whatsappText = urlencode($title . ' - FINOVATE ' . $url);

        return [
            'facebook'  => 'https://www.facebook.com/sharer/sharer.php?u=' . $encodedUrl . '&quote=' . $encodedTitle,
            'twitter'   => 'https://twitter.com/intent/tweet?text=' . $encodedTitle . '&url=' . $encodedUrl . '&hashtags=FINOVATE,Finance',
            'whatsapp'  => 'https://wa.me/?text=' . $whatsappText,
            'linkedin'  => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $encodedUrl . '&title=' . $encodedTitle,
        ];
    }
}