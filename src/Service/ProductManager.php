<?php

namespace App\Service;

use App\Entity\Product;

/**
 * Service métier pour la validation des règles métier de l'entité Product.
 */
class ProductManager
{
    /**
     * Valide les règles métier d'un produit.
     *
     * Règles validées:
     * 1. Le nom est obligatoire
     * 2. Le prix en points doit être positif
     * 3. Le stock doit être >= 0
     *
     * @param Product $product Le produit à valider
     * @return bool True si le produit est valide
     * @throws \InvalidArgumentException Si une règle métier n'est pas respectée
     */
    public function validate(Product $product): bool
    {
        // Règle 1: Le nom est obligatoire
        $name = trim($product->getName() ?? '');
        if (empty($name)) {
            throw new \InvalidArgumentException('Le nom du produit est obligatoire.');
        }

        // Règle 2: Le prix en points doit être positif
        $pricePoints = $product->getPricePoints();
        if ($pricePoints === null || $pricePoints <= 0) {
            throw new \InvalidArgumentException('Le prix en points doit être supérieur à 0.');
        }

        // Règle 3: Le stock doit être >= 0
        $stock = $product->getStock();
        if ($stock === null || $stock < 0) {
            throw new \InvalidArgumentException('Le stock ne peut pas être négatif.');
        }

        return true;
    }

    /**
     * Vérifie si le nom est valide.
     *
     * @param Product $product Le produit à vérifier
     * @return bool True si le nom est valide
     */
    public function isNameValid(Product $product): bool
    {
        $name = trim($product->getName() ?? '');
        return !empty($name);
    }

    /**
     * Vérifie si le prix en points est valide (> 0).
     *
     * @param Product $product Le produit à vérifier
     * @return bool True si le prix est valide
     */
    public function isPriceValid(Product $product): bool
    {
        $pricePoints = $product->getPricePoints();
        return $pricePoints !== null && $pricePoints > 0;
    }

    /**
     * Vérifie si le stock est valide (>= 0).
     *
     * @param Product $product Le produit à vérifier
     * @return bool True si le stock est valide
     */
    public function isStockValid(Product $product): bool
    {
        $stock = $product->getStock();
        return $stock !== null && $stock >= 0;
    }

    /**
     * Vérifie si le produit est disponible en stock.
     *
     * @param Product $product Le produit à vérifier
     * @return bool True si le produit est disponible
     */
    public function isAvailable(Product $product): bool
    {
        $stock = $product->getStock();
        return $stock !== null && $stock > 0;
    }

    /**
     * Vérifie si un utilisateur peut acheter le produit.
     *
     * @param Product $product Le produit
     * @param int $userPoints Les points de l'utilisateur
     * @return bool True si l'utilisateur peut acheter
     */
    public function canPurchase(Product $product, int $userPoints): bool
    {
        if (!$this->isAvailable($product)) {
            return false;
        }

        $pricePoints = $product->getPricePoints();
        return $pricePoints !== null && $userPoints >= $pricePoints;
    }

    /**
     * Calcule le nombre d'achats possibles avec les points de l'utilisateur.
     *
     * @param Product $product Le produit
     * @param int $userPoints Les points de l'utilisateur
     * @return int Le nombre d'achats possibles
     */
    public function getMaxPurchasable(Product $product, int $userPoints): int
    {
        $pricePoints = $product->getPricePoints() ?? 0;
        if ($pricePoints <= 0) {
            return 0;
        }

        $stock = $product->getStock() ?? 0;
        $affordable = (int) floor($userPoints / $pricePoints);

        return min($affordable, $stock);
    }

    /**
     * Réduit le stock du produit après un achat.
     *
     * @param Product $product Le produit
     * @param int $quantity La quantité achetée
     * @return bool True si le stock a été réduit avec succès
     */
    public function reduceStock(Product $product, int $quantity): bool
    {
        $currentStock = $product->getStock() ?? 0;
        if ($currentStock < $quantity) {
            return false;
        }

        $product->setStock($currentStock - $quantity);
        return true;
    }
}
