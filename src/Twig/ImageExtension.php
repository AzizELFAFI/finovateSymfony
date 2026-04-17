<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig extension for handling image URLs (local or Cloudinary)
 */
class ImageExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('image_url', [$this, 'imageUrl']),
        ];
    }

    /**
     * Convert image path to full URL
     * - If it's already a full URL (http/https), return as-is
     * - Otherwise, prepend with /
     */
    public function imageUrl(?string $path): string
    {
        if (!$path) {
            return '';
        }

        // If it's already a full URL, return as-is
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        // Local path - ensure it starts with /
        return '/' . ltrim($path, '/');
    }
}