<?php

namespace App\Cart;

class CartItem
{
    public function __construct(
        public readonly string  $article,
        public readonly string  $title,
        public readonly string  $brand,
        public readonly float   $price,
        public int              $quantity,
        public readonly ?string $image = null,
    ) {}

    public function getSubtotal(): float
    {
        return round($this->price * $this->quantity, 2);
    }
}
