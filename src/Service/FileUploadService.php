<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploadService
{
    private string $uploadPath;
    private array $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'jfif'];

    public function __construct(
        string $projectRoot,
        private ?CloudinaryService $cloudinary = null
    ) {
        $this->uploadPath = $projectRoot . '/public/uploads';
    }

    public function uploadImage(UploadedFile $file): string
    {
        // Valider l'extension du fichier
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $this->allowedExtensions, true)) {
            throw new \InvalidArgumentException(
                sprintf('Format de fichier non autorisé: %s. Extensions autorisées: %s', 
                    $extension, 
                    implode(', ', $this->allowedExtensions)
                )
            );
        }

        // Debug: Check if Cloudinary is injected
        if ($this->cloudinary === null) {
            error_log('⚠️ CloudinaryService is NULL - using local upload');
        } else {
            error_log('✅ CloudinaryService is available - attempting upload');
        }

        // Try to upload to Cloudinary first
        if ($this->cloudinary !== null) {
            try {
                error_log('📤 Uploading to Cloudinary...');
                $cloudinaryUrl = $this->cloudinary->upload($file, 'finovate/forum');
                error_log('✅ Cloudinary upload successful: ' . $cloudinaryUrl);
                return $cloudinaryUrl;
            } catch (\Throwable $e) {
                // Fallback to local upload if Cloudinary fails
                error_log('❌ Cloudinary upload failed: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
            }
        }

        // Fallback: Local upload
        error_log('💾 Falling back to local upload');
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        // Nettoyer le nom du fichier
        $originalFilename = preg_replace('/[^a-zA-Z0-9_-]/', '', $originalFilename);
        if (empty($originalFilename)) {
            $originalFilename = 'image';
        }

        $newFilename = sprintf(
            '%s-%s.%s',
            $originalFilename,
            bin2hex(random_bytes(8)),
            $extension
        );

        try {
            $file->move($this->uploadPath, $newFilename);
        } catch (FileException $e) {
            throw new \RuntimeException('Erreur lors de l\'upload du fichier: ' . $e->getMessage());
        }

        return '/uploads/' . $newFilename;
    }

    public function deleteImage(?string $imagePath): bool
    {
        if (!$imagePath) {
            return false;
        }

        // If it's a Cloudinary URL, we don't delete it (handled by Cloudinary)
        if (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
            return true;
        }

        // Local file deletion
        if (!str_starts_with($imagePath, '/uploads/')) {
            return false;
        }

        $fullPath = $this->uploadPath . str_replace('/uploads', '', $imagePath);

        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }

        return false;
    }
}
