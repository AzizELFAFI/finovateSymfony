<?php

namespace App\Repository;

use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    private const SORT_WHITELIST = [
        'id_desc',
        'goal_asc',
        'goal_desc',
        'current_asc',
        'current_desc',
        'deadline_asc',
        'deadline_desc',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * @return array{0: Project[], 1: int}
     */
    public function findPaginatedFiltered(int $page, int $perPage, string $search, string $statusFilter, string $sort): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        if (!in_array($sort, self::SORT_WHITELIST, true)) {
            $sort = 'id_desc';
        }

        $conn = $this->getEntityManager()->getConnection();

        $where = ['1=1'];
        $params = [];
        $types = [];

        if ($search !== '') {
            $where[] = 'title LIKE ?';
            $params[] = '%' . $search . '%';
            $types[] = ParameterType::STRING;
        }

        if ($statusFilter === 'OPEN') {
            $where[] = '(UPPER(TRIM(COALESCE(status, \'\'))) = \'OPEN\' OR status IS NULL OR TRIM(COALESCE(status, \'\')) = \'\')';
        } elseif ($statusFilter === 'CLOSED') {
            $where[] = 'status IN (\'COMPLETED\',\'CLOSED\',\'TERMINE\',\'TERMINÉ\')';
        }

        $whereSql = implode(' AND ', $where);

        $orderSql = match ($sort) {
            'goal_asc' => 'CAST(goal_amount AS DECIMAL(14,2)) ASC, id DESC',
            'goal_desc' => 'CAST(goal_amount AS DECIMAL(14,2)) DESC, id DESC',
            'current_asc' => 'CAST(COALESCE(current_amount, 0) AS DECIMAL(14,2)) ASC, id DESC',
            'current_desc' => 'CAST(COALESCE(current_amount, 0) AS DECIMAL(14,2)) DESC, id DESC',
            'deadline_asc' => 'deadline IS NULL, deadline ASC, id DESC',
            'deadline_desc' => 'deadline IS NOT NULL DESC, deadline DESC, id DESC',
            default => 'id DESC',
        };

        $countSql = 'SELECT COUNT(*) FROM project WHERE ' . $whereSql;
        $total = (int) $conn->fetchOne($countSql, $params, $types);

        $offset = ($page - 1) * $perPage;
        $idsSql = 'SELECT id FROM project WHERE ' . $whereSql . ' ORDER BY ' . $orderSql . ' LIMIT ? OFFSET ?';
        $allParams = array_merge($params, [$perPage, $offset]);
        $allTypes = array_merge($types, [ParameterType::INTEGER, ParameterType::INTEGER]);

        /** @var list<int|string> $ids */
        $ids = $conn->fetchFirstColumn($idsSql, $allParams, $allTypes);

        if ($ids === []) {
            return [[], $total];
        }

        $projects = $this->createQueryBuilder('p')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $byId = [];
        foreach ($projects as $p) {
            $byId[$p->getId()] = $p;
        }

        $ordered = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }

        return [$ordered, $total];
    }
}
