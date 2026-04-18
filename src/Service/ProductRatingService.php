<?php

namespace App\Service;

use App\Entity\ProductRating;
use App\Repository\ProductRatingRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProductRatingService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ProductRatingRepository $ratingRepo
    ) {}

    public function rateProduct(int $productId, string $userId, int $rating): array
    {
        if ($rating < 1 || $rating > 5) {
            return [
                'success' => false,
                'message' => 'La note doit être entre 1 et 5.',
            ];
        }

        $existingRating = $this->ratingRepo->findByProductAndUser($productId, $userId);

        if ($existingRating) {
            $existingRating->setRating($rating);
            $existingRating->setCreatedAt(new \DateTime());
            $this->em->flush();

            return [
                'success' => true,
                'message' => 'Votre note a été mise à jour.',
                'rating' => $rating,
                'average' => $this->ratingRepo->getAverageRating($productId),
                'total' => $this->ratingRepo->getTotalRatings($productId),
            ];
        }

        $productRating = new ProductRating();
        $productRating->setProductId($productId);
        $productRating->setUserId($userId);
        $productRating->setRating($rating);

        $this->em->persist($productRating);
        $this->em->flush();

        return [
            'success' => true,
            'message' => 'Votre note a été enregistrée.',
            'rating' => $rating,
            'average' => $this->ratingRepo->getAverageRating($productId),
            'total' => $this->ratingRepo->getTotalRatings($productId),
        ];
    }

    public function getProductRatingStats(int $productId): array
    {
        return [
            'average' => $this->ratingRepo->getAverageRating($productId),
            'total' => $this->ratingRepo->getTotalRatings($productId),
        ];
    }

    public function getUserRating(int $productId, string $userId): ?int
    {
        $rating = $this->ratingRepo->findByProductAndUser($productId, $userId);
        return $rating?->getRating();
    }
}
