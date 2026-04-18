<?php

namespace App\Service;

use App\Entity\ProductFavorite;
use App\Repository\ProductFavoriteRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProductFavoriteService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ProductFavoriteRepository $favoriteRepo
    ) {}

    public function toggleFavorite(int $productId, string $userId): array
    {
        $existing = $this->favoriteRepo->findByProductAndUser($productId, $userId);

        if ($existing) {
            $this->em->remove($existing);
            $this->em->flush();

            return [
                'success' => true,
                'isFavorite' => false,
                'message' => 'Retiré des favoris.',
                'count' => $this->favoriteRepo->getProductFavoriteCount($productId),
            ];
        }

        $favorite = new ProductFavorite();
        $favorite->setProductId($productId);
        $favorite->setUserId($userId);

        $this->em->persist($favorite);
        $this->em->flush();

        return [
            'success' => true,
            'isFavorite' => true,
            'message' => 'Ajouté aux favoris.',
            'count' => $this->favoriteRepo->getProductFavoriteCount($productId),
        ];
    }

    public function isFavorite(int $productId, string $userId): bool
    {
        return $this->favoriteRepo->isFavorite($productId, $userId);
    }

    public function getProductFavoriteCount(int $productId): int
    {
        return $this->favoriteRepo->getProductFavoriteCount($productId);
    }

    public function getUserFavorites(string $userId): array
    {
        return $this->favoriteRepo->getUserFavorites($userId);
    }
}
