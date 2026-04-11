<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploadService
{
    private string $uploadPath;
    private array $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public function __construct(string $projectRoot)
    {
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
        if (!$imagePath || !str_starts_with($imagePath, '/uploads/')) {
            return false;
        }

        $fullPath = $this->uploadPath . str_replace('/uploads', '', $imagePath);

        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }

        return false;
    }
}
