<?php

namespace App\Controller;

use App\Repository\ProductFavoriteRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/products', name: 'admin_products_')]
class AdminProductController extends AbstractController
{
    #[Route('/statistics', name: 'statistics')]
    public function statistics(
        ProductRepository $productRepo,
        ProductFavoriteRepository $favoriteRepo,
        EntityManagerInterface $em
    ): Response {
        // Basic counts
        $totalProducts = $productRepo->count([]);
        $inStockProducts = $productRepo->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.stock > 0')
            ->getQuery()
            ->getSingleScalarResult();
        $outOfStockProducts = $totalProducts - $inStockProducts;

        // Total stock value in points
        $totalStockValue = $productRepo->createQueryBuilder('p')
            ->select('SUM(p.stock * p.pricePoints)')
            ->getQuery()
            ->getSingleScalarResult() ?: 0;

        // Price distribution
        $priceRanges = [
            '0-100' => $productRepo->createQueryBuilder('p')->select('COUNT(p.id)')->where('p.pricePoints BETWEEN 0 AND 100')->getQuery()->getSingleScalarResult(),
            '101-500' => $productRepo->createQueryBuilder('p')->select('COUNT(p.id)')->where('p.pricePoints BETWEEN 101 AND 500')->getQuery()->getSingleScalarResult(),
            '501-1000' => $productRepo->createQueryBuilder('p')->select('COUNT(p.id)')->where('p.pricePoints BETWEEN 501 AND 1000')->getQuery()->getSingleScalarResult(),
            '1001+' => $productRepo->createQueryBuilder('p')->select('COUNT(p.id)')->where('p.pricePoints > 1000')->getQuery()->getSingleScalarResult(),
        ];

        // Top products by stock value
        $topProductsByValue = $productRepo->createQueryBuilder('p')
            ->select('p.name, p.stock, p.pricePoints, (p.stock * p.pricePoints) as totalValue')
            ->orderBy('totalValue', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        // Low stock products (stock < 10)
        $lowStockProducts = $productRepo->createQueryBuilder('p')
            ->where('p.stock > 0 AND p.stock < 10')
            ->orderBy('p.stock', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        // Most favorited products
        $mostFavorited = $em->createQuery('
            SELECT p.name, COUNT(pf.id) as favoriteCount
            FROM App\Entity\Product p
            LEFT JOIN App\Entity\ProductFavorite pf WITH pf.productId = p.id
            GROUP BY p.id
            ORDER BY favoriteCount DESC
        ')->setMaxResults(10)->getResult();

        // Stock distribution
        $stockDistribution = [
            'Rupture' => $outOfStockProducts,
            'Faible (1-10)' => $productRepo->createQueryBuilder('p')->select('COUNT(p.id)')->where('p.stock BETWEEN 1 AND 10')->getQuery()->getSingleScalarResult(),
            'Moyen (11-50)' => $productRepo->createQueryBuilder('p')->select('COUNT(p.id)')->where('p.stock BETWEEN 11 AND 50')->getQuery()->getSingleScalarResult(),
            'Élevé (50+)' => $productRepo->createQueryBuilder('p')->select('COUNT(p.id)')->where('p.stock > 50')->getQuery()->getSingleScalarResult(),
        ];

        // Average price
        $avgPrice = $productRepo->createQueryBuilder('p')
            ->select('AVG(p.pricePoints)')
            ->getQuery()
            ->getSingleScalarResult() ?: 0;

        // Total favorites
        $totalFavorites = $favoriteRepo->count([]);

        return $this->render('backoffice/product/statistics.html.twig', [
            'totalProducts' => $totalProducts,
            'inStockProducts' => $inStockProducts,
            'outOfStockProducts' => $outOfStockProducts,
            'totalStockValue' => $totalStockValue,
            'priceRanges' => $priceRanges,
            'topProductsByValue' => $topProductsByValue,
            'lowStockProducts' => $lowStockProducts,
            'mostFavorited' => $mostFavorited,
            'stockDistribution' => $stockDistribution,
            'avgPrice' => round($avgPrice, 2),
            'totalFavorites' => $totalFavorites,
        ]);
    }
}
