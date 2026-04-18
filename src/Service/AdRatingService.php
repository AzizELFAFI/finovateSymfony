<?php

namespace App\Service;

use App\Entity\AdRating;
use App\Repository\AdRatingRepository;
use Doctrine\ORM\EntityManagerInterface;

class AdRatingService
{
    public function __construct(
        private EntityManagerInterface $em,
        private AdRatingRepository $ratingRepo
    ) {}

    public function rateAd(int $adId, string $userId, int $rating): array
    {
        if ($rating < 1 || $rating > 5) {
            return [
                'success' => false,
                'message' => 'La note doit être entre 1 et 5.',
            ];
        }

        $existingRating = $this->ratingRepo->findByAdAndUser($adId, $userId);

        if ($existingRating) {
            $existingRating->setRating($rating);
            $existingRating->setCreatedAt(new \DateTime());
            $this->em->flush();

            return [
                'success' => true,
                'message' => 'Votre note a été mise à jour.',
                'rating' => $rating,
                'average' => $this->ratingRepo->getAverageRating($adId),
                'total' => $this->ratingRepo->getTotalRatings($adId),
            ];
        }

        $adRating = new AdRating();
        $adRating->setAdId($adId);
        $adRating->setUserId($userId);
        $adRating->setRating($rating);

        $this->em->persist($adRating);
        $this->em->flush();

        return [
            'success' => true,
            'message' => 'Votre note a été enregistrée.',
            'rating' => $rating,
            'average' => $this->ratingRepo->getAverageRating($adId),
            'total' => $this->ratingRepo->getTotalRatings($adId),
        ];
    }

    public function getAdRatingStats(int $adId): array
    {
        return [
            'average' => $this->ratingRepo->getAverageRating($adId),
            'total' => $this->ratingRepo->getTotalRatings($adId),
        ];
    }

    public function getUserRating(int $adId, string $userId): ?int
    {
        $rating = $this->ratingRepo->findByAdAndUser($adId, $userId);
        return $rating?->getRating();
    }
}
