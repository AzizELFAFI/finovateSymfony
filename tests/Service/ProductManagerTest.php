<?php

namespace App\Tests\Service;

use App\Entity\Product;
use App\Service\ProductManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le service ProductManager.
 * 
 * Règles métier testées:
 * 1. Le nom est obligatoire
 * 2. Le prix en points doit être positif
 * 3. Le stock doit être >= 0
 */
class ProductManagerTest extends TestCase
{
    private ProductManager $productManager;

    protected function setUp(): void
    {
        $this->productManager = new ProductManager();
    }

    /**
     * Crée un produit avec les propriétés spécifiées.
     */
    private function createProduct( $name, int $pricePoints, int $stock): Product
    {
        $product = new Product();
        $product->setName($name);
        $product->setPricePoints($pricePoints);
        $product->setStock($stock);
        
        return $product;
    }

    /**
     * Test: Produit valide avec toutes les règles respectées.
     */
    public function testValidProduct(): void
    {
        $product = $this->createProduct('Produit Test', 100, 50);
        
        $this->assertTrue($this->productManager->validate($product));
    }

    /**
     * Test: Nom vide (invalide).
     */
    public function testProductWithEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom du produit est obligatoire.');
        
        $product = $this->createProduct('', 100, 50);
        
        $this->productManager->validate($product);
    }

    /**
     * Test: Nom avec espaces uniquement (invalide).
     */
    public function testProductWithWhitespaceName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom du produit est obligatoire.');
        
        $product = $this->createProduct('   ', 100, 50);
        
        $this->productManager->validate($product);
    }

    /**
     * Test: Prix en points égal à 0 (invalide).
     */
    public function testProductWithZeroPrice(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prix en points doit être supérieur à 0.');
        
        $product = $this->createProduct('Produit Test', 0, 50);
        
        $this->productManager->validate($product);
    }

    /**
     * Test: Prix en points négatif (invalide).
     */
    public function testProductWithNegativePrice(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prix en points doit être supérieur à 0.');
        
        $product = $this->createProduct('Produit Test', -10, 50);
        
        $this->productManager->validate($product);
    }

    /**
     * Test: Stock négatif (invalide).
     */
    public function testProductWithNegativeStock(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le stock ne peut pas être négatif.');
        
        $product = $this->createProduct('Produit Test', 100, -5);
        
        $this->productManager->validate($product);
    }

    /**
     * Test: Stock égal à 0 (valide).
     */
    public function testProductWithZeroStock(): void
    {
        $product = $this->createProduct('Produit Test', 100, 0);
        
        $this->assertTrue($this->productManager->validate($product));
    }

    /**
     * Test: Méthode isNameValid avec nom valide.
     */
    public function testIsNameValidWithValidName(): void
    {
        $product = $this->createProduct('Produit Test', 100, 50);
        
        $this->assertTrue($this->productManager->isNameValid($product));
    }

    /**
     * Test: Méthode isNameValid avec nom invalide.
     */
    public function testIsNameValidWithInvalidName(): void
    {
        $product = $this->createProduct('', 100, 50);
        
        $this->assertFalse($this->productManager->isNameValid($product));
    }

    /**
     * Test: Méthode isPriceValid avec prix valide.
     */
    public function testIsPriceValidWithValidPrice(): void
    {
        $product = $this->createProduct('Produit Test', 100, 50);
        
        $this->assertTrue($this->productManager->isPriceValid($product));
    }

    /**
     * Test: Méthode isPriceValid avec prix invalide.
     */
    public function testIsPriceValidWithInvalidPrice(): void
    {
        $product = $this->createProduct('Produit Test', 0, 50);
        
        $this->assertFalse($this->productManager->isPriceValid($product));
    }

    /**
     * Test: Méthode isStockValid avec stock valide.
     */
    public function testIsStockValidWithValidStock(): void
    {
        $product = $this->createProduct('Produit Test', 100, 50);
        
        $this->assertTrue($this->productManager->isStockValid($product));
    }

    /**
     * Test: Méthode isStockValid avec stock négatif.
     */
    public function testIsStockValidWithNegativeStock(): void
    {
        $product = $this->createProduct('Produit Test', 100, -5);
        
        $this->assertFalse($this->productManager->isStockValid($product));
    }

    /**
     * Test: Méthode isAvailable avec stock > 0.
     */
    public function testIsAvailableWithStock(): void
    {
        $product = $this->createProduct('Produit Test', 100, 50);
        
        $this->assertTrue($this->productManager->isAvailable($product));
    }

    /**
     * Test: Méthode isAvailable avec stock = 0.
     */
    public function testIsAvailableWithZeroStock(): void
    {
        $product = $this->createProduct('Produit Test', 100, 0);
        
        $this->assertFalse($this->productManager->isAvailable($product));
    }

    /**
     * Test: Méthode canPurchase avec points suffisants.
     */
    public function testCanPurchaseWithSufficientPoints(): void
    {
        $product = $this->createProduct('Produit Test', 100, 50);
        
        $this->assertTrue($this->productManager->canPurchase($product, 150));
    }

    /**
     * Test: Méthode canPurchase avec points insuffisants.
     */
    public function testCanPurchaseWithInsufficientPoints(): void
    {
        $product = $this->createProduct('Produit Test', 100, 50);
        
        $this->assertFalse($this->productManager->canPurchase($product, 50));
    }

    /**
     * Test: Méthode canPurchase avec stock insuffisant.
     */
    public function testCanPurchaseWithNoStock(): void
    {
        $product = $this->createProduct('Produit Test', 100, 0);
        
        $this->assertFalse($this->productManager->canPurchase($product, 150));
    }

    /**
     * Test: Méthode getMaxPurchasable.
     */
    public function testGetMaxPurchasable(): void
    {
        $product = $this->createProduct('Produit Test', 100, 50);
        
        // Avec 250 points, on peut acheter 2 produits (250/100 = 2.5 -> 2)
        // Stock = 50, donc max = min(2, 50) = 2
        $this->assertEquals(2, $this->productManager->getMaxPurchasable($product, 250));
    }

    /**
     * Test: Méthode getMaxPurchasable limité par le stock.
     */
    public function testGetMaxPurchasableLimitedByStock(): void
    {
        $product = $this->createProduct('Produit Test', 100, 3);
        
        // Avec 1000 points, on peut acheter 10 produits
        // Mais stock = 3, donc max = 3
        $this->assertEquals(3, $this->productManager->getMaxPurchasable($product, 1000));
    }

    /**
     * Test: Méthode reduceStock.
     */
    public function testReduceStock(): void
    {
        $product = $this->createProduct('Produit Test', 100, 50);
        
        $this->assertTrue($this->productManager->reduceStock($product, 10));
        $this->assertEquals(40, $product->getStock());
    }

    /**
     * Test: Méthode reduceStock avec quantité supérieure au stock.
     */
    public function testReduceStockWithExcessiveQuantity(): void
    {
        $product = $this->createProduct('Produit Test', 100, 5);
        
        $this->assertFalse($this->productManager->reduceStock($product, 10));
        $this->assertEquals(5, $product->getStock()); // Stock inchangé
    }

    /**
     * Test: Méthode reduceStock à zéro.
     */
    public function testReduceStockToZero(): void
    {
        $product = $this->createProduct('Produit Test', 100, 10);
        
        $this->assertTrue($this->productManager->reduceStock($product, 10));
        $this->assertEquals(0, $product->getStock());
    }
}
