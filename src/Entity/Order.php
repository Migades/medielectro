<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')] // "order" es palabra reservada en SQL
class Order
{
    // Estados del pedido
    public const STATUS_PENDING   = 'pending';    // recibido, pendiente de confirmar
    public const STATUS_CONFIRMED = 'confirmed';  // confirmado por el equipo
    public const STATUS_SHIPPED   = 'shipped';    // enviado
    public const STATUS_DELIVERED = 'delivered';  // entregado
    public const STATUS_CANCELLED = 'cancelled';  // cancelado

    // Estados de sincronización con ERP
    public const ERP_PENDING = 'pending';
    public const ERP_SENT    = 'sent';
    public const ERP_ERROR   = 'error';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Número de pedido legible (ej: ME-2026-00001)
    #[ORM\Column(length: 30, unique: true)]
    private ?string $reference = null;

    #[ORM\ManyToOne(inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Customer $customer = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $total = null;

    // Dirección de entrega (snapshot en el momento del pedido)
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $shippingAddress = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $shippingZip = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $shippingCity = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    // Integración ERP
    #[ORM\Column(length: 20)]
    private string $erpStatus = self::ERP_PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $erpResponse = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $erpSentAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, OrderLine>
     */
    #[ORM\OneToMany(targetEntity: OrderLine::class, mappedBy: 'order', cascade: ['persist', 'remove'])]
    private Collection $lines;

    public function __construct()
    {
        $this->lines     = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getReference(): ?string { return $this->reference; }
    public function setReference(string $reference): static { $this->reference = $reference; return $this; }

    public function getCustomer(): ?Customer { return $this->customer; }
    public function setCustomer(?Customer $customer): static { $this->customer = $customer; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getTotal(): ?string { return $this->total; }
    public function setTotal(string $total): static { $this->total = $total; return $this; }

    public function getShippingAddress(): ?string { return $this->shippingAddress; }
    public function setShippingAddress(?string $shippingAddress): static { $this->shippingAddress = $shippingAddress; return $this; }

    public function getShippingZip(): ?string { return $this->shippingZip; }
    public function setShippingZip(?string $zip): static { $this->shippingZip = $zip; return $this; }

    public function getShippingCity(): ?string { return $this->shippingCity; }
    public function setShippingCity(?string $city): static { $this->shippingCity = $city; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getErpStatus(): string { return $this->erpStatus; }
    public function setErpStatus(string $erpStatus): static { $this->erpStatus = $erpStatus; return $this; }

    public function getErpResponse(): ?string { return $this->erpResponse; }
    public function setErpResponse(?string $erpResponse): static { $this->erpResponse = $erpResponse; return $this; }

    public function getErpSentAt(): ?\DateTimeImmutable { return $this->erpSentAt; }
    public function setErpSentAt(?\DateTimeImmutable $erpSentAt): static { $this->erpSentAt = $erpSentAt; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    /** @return Collection<int, OrderLine> */
    public function getLines(): Collection { return $this->lines; }

    public function addLine(OrderLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setOrder($this);
        }
        return $this;
    }

    public function removeLine(OrderLine $line): static
    {
        if ($this->lines->removeElement($line)) {
            if ($line->getOrder() === $this) {
                $line->setOrder(null);
            }
        }
        return $this;
    }

    /** Recalcula el total sumando las líneas */
    public function recalcTotal(): void
    {
        $total = array_sum(
            $this->lines->map(fn(OrderLine $l) => $l->getSubtotal())->toArray()
        );
        $this->total = (string) round($total, 2);
    }
}
