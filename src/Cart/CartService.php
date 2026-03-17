<?php

namespace App\Cart;

use App\Entity\Product;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Gestiona el carrito en la sesión Symfony.
 *
 * Clave de sesión: 'cart' → array<string, CartItem> indexado por article.
 *
 * TODO: mergeSessionCartToUser(User $user) cuando se implemente el login.
 */
class CartService
{
    private const SESSION_KEY = 'cart';

    public function __construct(private readonly RequestStack $requestStack) {}

    /** @return array<string, CartItem> */
    public function getItems(): array
    {
        return $this->getSession()->get(self::SESSION_KEY, []);
    }

    public function add(Product $product, int $quantity = 1): void
    {
        $items = $this->getItems();
        $key   = $product->getArticle();

        if (isset($items[$key])) {
            $items[$key]->quantity += $quantity;
        } else {
            $items[$key] = new CartItem(
                article:  $product->getArticle(),
                title:    $product->getTitle() ?? $product->getModel(),
                brand:    $product->getBrand() ?? '',
                price:    (float) $product->getPrice(),
                quantity: $quantity,
                image:    $product->getImage(),
            );
        }

        $this->save($items);
    }

    public function updateQuantity(string $article, int $quantity): void
    {
        $items = $this->getItems();

        if (!isset($items[$article])) {
            return;
        }

        if ($quantity <= 0) {
            $this->remove($article);
            return;
        }

        $items[$article]->quantity = $quantity;
        $this->save($items);
    }

    public function remove(string $article): void
    {
        $items = $this->getItems();
        unset($items[$article]);
        $this->save($items);
    }

    public function clear(): void
    {
        $this->save([]);
    }

    public function count(): int
    {
        return array_sum(array_map(
            fn(CartItem $item) => $item->quantity,
            $this->getItems()
        ));
    }

    public function getTotal(): float
    {
        return round(array_sum(array_map(
            fn(CartItem $item) => $item->getSubtotal(),
            $this->getItems()
        )), 2);
    }

    public function isEmpty(): bool
    {
        return count($this->getItems()) === 0;
    }

    /** @param array<string, CartItem> $items */
    private function save(array $items): void
    {
        $this->getSession()->set(self::SESSION_KEY, $items);
    }

    private function getSession(): \Symfony\Component\HttpFoundation\Session\SessionInterface
    {
        return $this->requestStack->getSession();
    }
}
