<?php

namespace App\Entity;

use App\Repository\FamilyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FamilyRepository::class)]
class Family
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    /**
     * @var Collection<int, Subfamily>
     */
    #[ORM\OneToMany(targetEntity: Subfamily::class, mappedBy: 'family')]
    private Collection $subfamilies;

    /**
     * @var Collection<int, Product>
     */
    #[ORM\OneToMany(targetEntity: Product::class, mappedBy: 'family')]
    private Collection $products;

    public function __construct()
    {
        $this->subfamilies = new ArrayCollection();
        $this->products = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * @return Collection<int, Subfamily>
     */
    public function getSubfamilies(): Collection
    {
        return $this->subfamilies;
    }

    public function addSubfamily(Subfamily $subfamily): static
    {
        if (!$this->subfamilies->contains($subfamily)) {
            $this->subfamilies->add($subfamily);
            $subfamily->setFamily($this);
        }

        return $this;
    }

    public function removeSubfamily(Subfamily $subfamily): static
    {
        if ($this->subfamilies->removeElement($subfamily)) {
            // set the owning side to null (unless already changed)
            if ($subfamily->getFamily() === $this) {
                $subfamily->setFamily(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
            $product->setFamily($this);
        }

        return $this;
    }

    public function removeProduct(Product $product): static
    {
        if ($this->products->removeElement($product)) {
            // set the owning side to null (unless already changed)
            if ($product->getFamily() === $this) {
                $product->setFamily(null);
            }
        }

        return $this;
    }
}
