<?php

namespace App\Repository;

use App\Entity\FlaggedContent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FlaggedContentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FlaggedContent::class);
    }

    /**
     * Returns all unreviewed and non-ignored flagged items, newest first.
     */
    public function findUnreviewed(): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.reviewed = false')
            ->andWhere('f.ignored = false')
            ->orderBy('f.detectedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns all flagged items of a given flagType (e.g. 'misinformation', 'toxic').
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.flagType = :type')
            ->setParameter('type', $type)
            ->orderBy('f.detectedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Checks whether a FlaggedContent record already exists for the given content.
     */
    public function existsForContent(string $contentType, int $contentId): bool
    {
        $count = (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.contentType = :ct')
            ->andWhere('f.contentId = :cid')
            ->setParameter('ct', $contentType)
            ->setParameter('cid', $contentId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
