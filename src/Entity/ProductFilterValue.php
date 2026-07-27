<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'product_filter_value')]
#[ORM\UniqueConstraint(name: 'uniq_filter_value_product', columns: ['product_filter_id', 'product_id', 'value'])]
class ProductFilterValue extends AbstractEntity
{
    #[ORM\ManyToOne(targetEntity: ProductFilter::class, inversedBy: 'values')]
    #[ORM\JoinColumn(name: 'product_filter_id', nullable: false, onDelete: 'CASCADE')]
    private ProductFilter $filter;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'filterValues')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Product $product;

    #[ORM\Column(length: 120)]
    private string $value;

    public function __construct(ProductFilter $filter, ?Product $product, string $value)
    {
        parent::__construct();
        $this->filter = $filter;
        $this->product = $product;
        $this->value = $value;
    }

    public function getFilter(): ProductFilter
    {
        return $this->filter;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
