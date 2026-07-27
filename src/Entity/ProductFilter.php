<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'product_filter')]
#[ORM\UniqueConstraint(name: 'uniq_filter_boutique_slug', columns: ['boutique_id', 'slug'])]
class ProductFilter extends AbstractEntity
{
    #[ORM\ManyToOne(targetEntity: Boutique::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Boutique $boutique;

    #[ORM\Column(length: 80)]
    private string $name;

    #[ORM\Column(length: 80)]
    private string $slug;

    #[ORM\Column(length: 30)]
    private string $type;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\OneToMany(mappedBy: 'filter', targetEntity: ProductFilterValue::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $values;

    public function __construct(Boutique $boutique, string $name, string $slug, string $type)
    {
        parent::__construct();
        $this->boutique = $boutique;
        $this->name = $name;
        $this->slug = $slug;
        $this->type = $type;
        $this->values = new ArrayCollection();
    }

    public function getBoutique(): Boutique
    {
        return $this->boutique;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getValues(): Collection
    {
        return $this->values;
    }

    public function addValue(ProductFilterValue $value): void
    {
        if (!$this->values->contains($value)) {
            $this->values->add($value);
        }
    }

    public function removeValue(ProductFilterValue $value): void
    {
        $this->values->removeElement($value);
    }
}
