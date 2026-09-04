<?php

namespace DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service;

use DovStone\Bundle\SymfonyBlogAdminBundleQuantBased\Service\PleaseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class MarketplaceService extends AbstractController
{
    public $please;
    public $session;
    public $basket;

    // Données de la variation courante
    private $currentVariation;
    private $currentProductId;
    private $currentVariations;
    private $currentAmounts;
    private $currentVariationId;
    private $stockInfo;

    public function __construct(PleaseService $please)
    {
        $this->please = $please;
        $this->session = $please->getService('session');
    }

    /**
     * Initialise le service avec une variation et un produit
     */
    public function init($variation, $productId)
    {
        $this->currentVariation = $variation;
        $this->currentProductId = $productId;

        // Extraire les variations
        if (isset($variation['variations']) && is_array($variation['variations'])) {
            $this->currentVariations = $variation['variations'];
            $this->currentAmounts = $variation['amounts'] ?? [];
        } else {
            $this->currentVariations = $variation;
            $this->currentAmounts = [];
        }

        $this->currentVariationId = $this->generateVariationId($this->currentVariations);
        $this->stockInfo = $this->getVariationStockInfo($this->currentVariations, $productId);

        return $this;
    }

    /**
     * Génère un ID unique pour une combinaison de variations
     */
    private function generateVariationId($variations)
    {
        if (is_array($variations)) {
            return md5(json_encode($variations));
        }
        return $variations;
    }

    /**
     * Récupère le panier actuel ou crée un nouveau
     */
    public function currentBasket()
    {
        if (!$this->session->get('basket')) {
            $this->session->set('basket', [
                'variations' => [],
                'qties' => 0,
                'subTotal' => 0,
                'total' => 0
            ]);
        }
        return $this->session->get('basket');
    }

    /**
     * Récupère la quantité disponible et la quantité max par commande
     */
    private function getVariationStockInfo($variation, $productId)
    {
        $product = $this->please->pop(collection('product')->find($productId), []);
        if (!$product) {
            return ['available' => 0, 'max_per_order' => 0];
        }

        $variationsOptions = $product['variations_options'] ?? [];
        foreach ($variationsOptions as $option) {
            $optionVariations = $option['variations'] ?? [];
            if ($this->compareVariations($optionVariations, $variation)) {
                return [
                    'available' => $option['amounts']['qte_disponible'] ?? 0,
                    'max_per_order' => $option['amounts']['qte_max_commande'] ?? $option['amounts']['qte_disponible'] ?? 0
                ];
            }
        }

        return ['available' => 0, 'max_per_order' => 0];
    }

    /**
     * Compare deux tableaux de variations
     */
    private function compareVariations($a, $b)
    {
        if (empty($a) || empty($b)) {
            return false;
        }
        ksort($a);
        ksort($b);
        return $a === $b;
    }

    /**
     * Met à jour le panier (ajout/retrait)
     */
    public function updateBasket($variationId)
    {
        $variation = $this->decodeVariation($variationId);
        $productId = $variation['productId'] ?? null;
        $data = $variation['data'] ?? [];
        $variations = $data['variations'] ?? [];
        $amounts = $data['amounts'] ?? [];
        $action = $variation['action'] ?? 'dec';

        $variationsID = $this->generateVariationId($variations);

        $basket = $this->currentBasket();
        $currentVariations = $basket['variations'];

        $currentQty = $currentVariations[$variationsID]['qty'] ?? 0;
        $stockInfo = $this->getVariationStockInfo($variations, $productId);
        $availableQty = $stockInfo['available'];
        $maxPerOrder = $stockInfo['max_per_order'];

        if ($action === 'dec') {
            $newQty = max(0, $currentQty - 1);
        } else {
            if ($currentQty >= $availableQty || $currentQty >= $maxPerOrder) {
                return $this->currentBasket();
            }
            $newQty = $currentQty + 1;
        }

        if ($newQty <= 0) {
            unset($currentVariations[$variationsID]);
        } else {
            $currentVariations[$variationsID] = [
                'id' => $variationsID,
                'productId' => $productId,
                'variations' => $variations,
                'amounts' => $amounts,
                'qty' => $newQty,
                'availableQty' => $availableQty,
                'maxPerOrder' => $maxPerOrder
            ];
        }

        $totals = $this->calculateTotals($currentVariations);

        $newBasket = [
            'variations' => $currentVariations,
            'qties' => $totals['qties'],
            'subTotal' => $totals['subTotal'],
            'total' => $totals['total']
        ];

        $this->session->set('basket', $newBasket);

        return $newBasket;
    }

    /**
     * Calcule les totaux du panier
     */
    public function calculateTotals($variations)
    {
        $qties = 0;
        $subTotal = 0;
        $total = 0;

        foreach ($variations as $variation) {
            $qty = $variation['qty'] ?? 0;
            $price = $variation['amounts']['prix_de_vente'] ?? $variation['amounts']['prix_regulier'] ?? 0;

            $qties += $qty;
            $subTotal += $price * $qty;
            $total += $price * $qty;
        }

        return [
            'qties' => $qties,
            'subTotal' => $subTotal,
            'total' => $total
        ];
    }

    // ============================================
    // Méthodes utilisables après init()
    // ============================================

    /**
     * Récupère la quantité dans le panier
     */
    public function getVariationQty()
    {
        $basket = $this->currentBasket();
        if (isset($basket['variations'][$this->currentVariationId])) {
            $basketProductId = $basket['variations'][$this->currentVariationId]['productId'] ?? null;
            if ($basketProductId == $this->currentProductId) {
                return $basket['variations'][$this->currentVariationId]['qty'] ?? 0;
            }
        }
        return 0;
    }

    /**
     * Récupère la quantité disponible
     */
    public function getAvailableQty()
    {
        return $this->stockInfo['available'] ?? 0;
    }

    /**
     * Récupère la quantité max par commande
     */
    public function getMaxPerOrder()
    {
        return $this->stockInfo['max_per_order'] ?? 0;
    }

    /**
     * Vérifie si la quantité max par commande est atteinte
     */
    public function isMaxPerOrderReached()
    {
        $currentQty = $this->getVariationQty();
        $maxPerOrder = $this->getMaxPerOrder();
        return $currentQty >= $maxPerOrder;
    }

    /**
     * Vérifie si le stock est atteint
     */
    public function isStockReached()
    {
        $currentQty = $this->getVariationQty();
        $availableQty = $this->getAvailableQty();
        return $currentQty >= $availableQty;
    }

    /**
     * Vérifie si la variation est en stock
     */
    public function isInStock()
    {
        return $this->getAvailableQty() > 0;
    }

    /**
     * Vérifie si l'ajout est possible
     */
    public function canAdd()
    {
        return !$this->isStockReached() && !$this->isMaxPerOrderReached() && $this->isInStock();
    }

    /**
     * Vérifie si la variation est dans le panier
     */
    public function isInBasket()
    {
        $basket = $this->currentBasket();
        if (isset($basket['variations'][$this->currentVariationId])) {
            $basketProductId = $basket['variations'][$this->currentVariationId]['productId'] ?? null;
            return $basketProductId == $this->currentProductId;
        }
        return false;
    }

    /**
     * Crypte la variation courante
     */
    public function encrypt($action = 'dec')
    {
        $data = $this->currentVariation;
        if (isset($data['title'])) {
            unset($data['title']);
        }

        $payload = [
            'productId' => $this->currentProductId,
            'data' => $data,
            'action' => $action,
            'timestamp' => time()
        ];
        return base64_encode(json_encode($payload));
    }

    // ============================================
    // Méthodes statiques / globales
    // ============================================

    /**
     * Vérifie si une variation est dans le panier (méthode statique)
     */
    public function isInBasketStatic($variation, $productId)
    {
        if (isset($variation['variations']) && is_array($variation['variations'])) {
            $variations = $variation['variations'];
        } else {
            $variations = $variation;
        }

        $variationsID = $this->generateVariationId($variations);
        $basket = $this->currentBasket();

        if (isset($basket['variations'][$variationsID])) {
            $basketProductId = $basket['variations'][$variationsID]['productId'] ?? null;
            return $basketProductId == $productId;
        }

        return false;
    }

    /**
     * Vide le panier
     */
    public function clearBasket()
    {
        $this->session->set('basket', [
            'variations' => [],
            'qties' => 0,
            'subTotal' => 0,
            'total' => 0
        ]);
        return $this->currentBasket();
    }

    /**
     * Supprime une variation du panier (méthode statique)
     */
    public function removeVariationStatic($variation, $productId)
    {
        if (isset($variation['variations']) && is_array($variation['variations'])) {
            $variations = $variation['variations'];
        } else {
            $variations = $variation;
        }

        $variationsID = $this->generateVariationId($variations);
        $basket = $this->currentBasket();
        $currentVariations = $basket['variations'];

        if (isset($currentVariations[$variationsID])) {
            $basketProductId = $currentVariations[$variationsID]['productId'] ?? null;
            if ($basketProductId == $productId) {
                unset($currentVariations[$variationsID]);
            }
        }

        $totals = $this->calculateTotals($currentVariations);

        $newBasket = [
            'variations' => $currentVariations,
            'qties' => $totals['qties'],
            'subTotal' => $totals['subTotal'],
            'total' => $totals['total']
        ];

        $this->session->set('basket', $newBasket);
        return $newBasket;
    }

    /**
     * Crypte une variation (méthode statique)
     */
    public function encryptVariation($variation, $productId, $action = 'dec')
    {
        if (isset($variation['variations']) && is_array($variation['variations'])) {
            $data = $variation;
        } else {
            $data = ['variations' => $variation, 'amounts' => []];
        }

        if (isset($data['title'])) {
            unset($data['title']);
        }

        $payload = [
            'productId' => $productId,
            'data' => $data,
            'action' => $action,
            'timestamp' => time()
        ];
        return base64_encode(json_encode($payload));
    }

    /**
     * Décrypte une variation
     */
    public function decodeVariation($variationId)
    {
        $decoded = json_decode(base64_decode($variationId), true);
        return [
            'productId' => $decoded['productId'] ?? null,
            'data' => $decoded['data'] ?? [],
            'action' => $decoded['action'] ?? 'dec',
            'timestamp' => $decoded['timestamp'] ?? null
        ];
    }

    /**
     * Résumé du panier pour le front
     */
    public function getBasketSummary()
    {
        $basket = $this->currentBasket();
        return [
            'count' => $basket['qties'],
            'total' => $basket['total'],
            'items' => $basket['variations']
        ];
    }

    /**
     * Récupère toutes les variations du panier
     */
    public function getBasketVariations()
    {
        $basket = $this->currentBasket();
        return $basket['variations'] ?? [];
    }

    /**
     * Récupère les variations d'un produit spécifique
     */
    public function getProductVariations($productId)
    {
        $basket = $this->currentBasket();
        $result = [];

        foreach ($basket['variations'] as $variation) {
            if (($variation['productId'] ?? null) == $productId) {
                $result[] = $variation;
            }
        }

        return $result;
    }

    /**
     * Supprime toutes les variations d'un produit
     */
    public function removeProductFromBasket($productId)
    {
        $basket = $this->currentBasket();
        $currentVariations = $basket['variations'];

        foreach ($currentVariations as $key => $variation) {
            if (($variation['productId'] ?? null) == $productId) {
                unset($currentVariations[$key]);
            }
        }

        $totals = $this->calculateTotals($currentVariations);

        $newBasket = [
            'variations' => $currentVariations,
            'qties' => $totals['qties'],
            'subTotal' => $totals['subTotal'],
            'total' => $totals['total']
        ];

        $this->session->set('basket', $newBasket);
        return $newBasket;
    }

    /**
     * Récupère le total du panier
     */
    public function getBasketTotal()
    {
        $basket = $this->currentBasket();
        return $basket['total'] ?? 0;
    }

    /**
     * Récupère le nombre d'articles dans le panier
     */
    public function getBasketCount()
    {
        $basket = $this->currentBasket();
        return $basket['qties'] ?? 0;
    }

    /**
     * Vérifie si le panier est vide
     */
    public function isBasketEmpty()
    {
        $basket = $this->currentBasket();
        return empty($basket['variations']) || $basket['qties'] == 0;
    }
}
