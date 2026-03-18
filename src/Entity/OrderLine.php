<?php

namespace App\Entity;

use App\Repository\OrderLineRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderLineRepository::class)]
class OrderLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Order $order = null;

    // Snapshot del producto en el momento del pedido — no FK para evitar problemas si el producto se elimina
    #[ORM\Column(length: 100)]
    private ?string $productArticle = null;

    #[ORM\Column(length: 255)]
    private ?string $productTitle = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $productBrand = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $unitPrice = null;

    #[ORM\Column]
    private ?int $quantity = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $subtotal = null;

    public function getId(): ?int { return $this->id; }

    public function getOrder(): ?Order { return $this->order; }
    public function setOrder(?Order $order): static { $this->order = $order; return $this; }

    public function getProductArticle(): ?string { return $this->productArticle; }
    public function setProductArticle(string $productArticle): static { $this->productArticle = $productArticle; return $this; }

    public function getProductTitle(): ?string { return $this->productTitle; }
    public function setProductTitle(string $productTitle): static { $this->productTitle = $productTitle; return $this; }

    public function getProductBrand(): ?string { return $this->productBrand; }
    public function setProductBrand(?string $productBrand): static { $this->productBrand = $productBrand; return $this; }

    public function getUnitPrice(): ?string { return $this->unitPrice; }
    public function setUnitPrice(string $unitPrice): static { $this->unitPrice = $unitPrice; return $this; }

    public function getQuantity(): ?int { return $this->quantity; }
    public function setQuantity(int $quantity): static { $this->quantity = $quantity; return $this; }

    public function getSubtotal(): float { return (float) $this->subtotal; }
    public function setSubtotal(string $subtotal): static { $this->subtotal = $subtotal; return $this; }

    /** Calcula y guarda el subtotal */
    public function calcSubtotal(): void
    {
        $this->subtotal = (string) round((float) $this->unitPrice * $this->quantity, 2);
    }
}
