<?php

namespace App\Entity;

use App\Doctrine\Traits\SoftDeleteTrait;
use App\Entity\Contract\SoftDeletableInterface;
use App\Enum\ProductStatus;
use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'product')]
#[ORM\UniqueConstraint(name: 'uniq_product_boutique_sku', columns: ['boutique_id', 'sku'])]
#[ORM\UniqueConstraint(name: 'uniq_product_boutique_slug', columns: ['boutique_id', 'slug'])]
class Product extends AbstractEntity implements SoftDeletableInterface
{
    use SoftDeleteTrait;

    /** @var Collection<int, ProductImage> */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductImage::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $images;

    /** @var Collection<int, ProductFilterValue> */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductFilterValue::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $filterValues;

    /** @var Collection<int, Review> */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: Review::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $reviews;

    #[ORM\OneToOne(mappedBy: 'product', targetEntity: ProductStock::class, cascade: ['persist', 'remove'])]
    private ?ProductStock $stock = null;

    /** @var Collection<int, ProductMedia> */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductMedia::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $media;

    /** @var Collection<int, ProductVariant> */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductVariant::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $variants;

    /** @var Collection<int, ProductProperty> */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductProperty::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $properties;

    /** @var Collection<int, ProductCategory> */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductCategory::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $productCategories;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Boutique::class, inversedBy: 'products')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Boutique $boutique,
        #[ORM\Column(length: 180)]
        private string $name,
        #[ORM\Column(length: 200)]
        private string $slug,
        #[ORM\Column(length: 80)]
        private string $sku,
        #[ORM\Column(length: 80, nullable: true)]
        private ?string $barcode = null,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $shortDescription = null,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $description = null,
        #[ORM\Column(length: 32, enumType: ProductStatus::class)]
        private ProductStatus $status = ProductStatus::Draft,
        #[ORM\Column]
        private int $costPrice = 0,
        #[ORM\Column]
        private int $sellingPrice = 0,
        #[ORM\Column]
        private int $comparePrice = 0,
        #[ORM\Column]
        private int $taxRate = 0,
        #[ORM\Column]
        private int $weight = 0,
        #[ORM\Column]
        private int $length = 0,
        #[ORM\Column]
        private int $width = 0,
        #[ORM\Column]
        private int $height = 0,
        #[ORM\Column]
        private bool $manageStock = true,
        #[ORM\Column]
        private int $stockQuantity = 0,
        #[ORM\Column]
        private int $lowStockThreshold = 5,
        #[ORM\Column]
        private bool $isFeatured = false,
        #[ORM\Column]
        private bool $isBestSeller = false,
        #[ORM\Column]
        private bool $isNew = false,
        #[ORM\Column]
        private bool $isVirtual = false,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $metaTitle = null,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $metaDescription = null,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $metaKeywords = null,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $ogTitle = null,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $ogDescription = null,
        #[ORM\Column(length: 500, nullable: true)]
        private ?string $ogImage = null,
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $publishedAt = null,
        #[ORM\ManyToOne(targetEntity: Brand::class, inversedBy: 'products')]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?Brand $brand = null,
        #[ORM\Column(length: 3)]
        private string $currency = 'TND',
        #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'products')]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?Category $category = null,
        #[ORM\Column]
        private int $viewsCount = 0,
        #[ORM\Column]
        private \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $updatedAt = null,
    ) {
        parent::__construct();
        $this->images = new ArrayCollection();
        $this->filterValues = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->media = new ArrayCollection();
        $this->variants = new ArrayCollection();
        $this->properties = new ArrayCollection();
        $this->productCategories = new ArrayCollection();
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
        $this->touch();
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
        $this->touch();
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function setSku(string $sku): void
    {
        $this->sku = $sku;
        $this->touch();
    }

    public function getBarcode(): ?string
    {
        return $this->barcode;
    }

    public function setBarcode(?string $barcode): void
    {
        $this->barcode = $barcode;
        $this->touch();
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $desc): void
    {
        $this->shortDescription = $desc;
        $this->touch();
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
        $this->touch();
    }

    public function getStatus(): ProductStatus
    {
        return $this->status;
    }

    public function setStatus(ProductStatus $status): void
    {
        $this->status = $status;
        $this->touch();
    }

    public function isActive(): bool
    {
        return ProductStatus::Active === $this->status;
    }

    public function getViewsCount(): int
    {
        return $this->viewsCount;
    }

    public function incrementViews(): void
    {
        ++$this->viewsCount;
    }

    public function getCostPrice(): int
    {
        return $this->costPrice;
    }

    public function setCostPrice(int $price): void
    {
        $this->costPrice = $price;
        $this->touch();
    }

    public function getSellingPrice(): int
    {
        return $this->sellingPrice;
    }

    public function setSellingPrice(int $price): void
    {
        $this->sellingPrice = $price;
        $this->touch();
    }

    public function getComparePrice(): int
    {
        return $this->comparePrice;
    }

    public function setComparePrice(int $price): void
    {
        $this->comparePrice = $price;
        $this->touch();
    }

    public function getTaxRate(): int
    {
        return $this->taxRate;
    }

    public function setTaxRate(int $rate): void
    {
        $this->taxRate = $rate;
        $this->touch();
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function setWeight(int $weight): void
    {
        $this->weight = $weight;
        $this->touch();
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function setLength(int $length): void
    {
        $this->length = $length;
        $this->touch();
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function setWidth(int $width): void
    {
        $this->width = $width;
        $this->touch();
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function setHeight(int $height): void
    {
        $this->height = $height;
        $this->touch();
    }

    public function getManageStock(): bool
    {
        return $this->manageStock;
    }

    public function setManageStock(bool $manage): void
    {
        $this->manageStock = $manage;
        $this->touch();
    }

    public function getStockQuantity(): int
    {
        return $this->stockQuantity;
    }

    public function setStockQuantity(int $qty): void
    {
        $this->stockQuantity = $qty;
        $this->touch();
    }

    public function getLowStockThreshold(): int
    {
        return $this->lowStockThreshold;
    }

    public function setLowStockThreshold(int $threshold): void
    {
        $this->lowStockThreshold = $threshold;
        $this->touch();
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $featured): void
    {
        $this->isFeatured = $featured;
        $this->touch();
    }

    public function isBestSeller(): bool
    {
        return $this->isBestSeller;
    }

    public function setIsBestSeller(bool $best): void
    {
        $this->isBestSeller = $best;
        $this->touch();
    }

    public function isNew(): bool
    {
        return $this->isNew;
    }

    public function setIsNew(bool $new): void
    {
        $this->isNew = $new;
        $this->touch();
    }

    public function isVirtual(): bool
    {
        return $this->isVirtual;
    }

    public function setIsVirtual(bool $virtual): void
    {
        $this->isVirtual = $virtual;
        $this->touch();
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $title): void
    {
        $this->metaTitle = $title;
        $this->touch();
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $desc): void
    {
        $this->metaDescription = $desc;
        $this->touch();
    }

    public function getMetaKeywords(): ?string
    {
        return $this->metaKeywords;
    }

    public function setMetaKeywords(?string $keywords): void
    {
        $this->metaKeywords = $keywords;
        $this->touch();
    }

    public function getOgTitle(): ?string
    {
        return $this->ogTitle;
    }

    public function setOgTitle(?string $title): void
    {
        $this->ogTitle = $title;
        $this->touch();
    }

    public function getOgDescription(): ?string
    {
        return $this->ogDescription;
    }

    public function setOgDescription(?string $description): void
    {
        $this->ogDescription = $description;
        $this->touch();
    }

    public function getOgImage(): ?string
    {
        return $this->ogImage;
    }

    public function setOgImage(?string $image): void
    {
        $this->ogImage = $image;
        $this->touch();
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $at): void
    {
        $this->publishedAt = $at;
        $this->touch();
    }

    public function getBrand(): ?Brand
    {
        return $this->brand;
    }

    public function setBrand(?Brand $brand): void
    {
        $this->brand = $brand;
        $this->touch();
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
        $this->touch();
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): void
    {
        $this->category = $category;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getStock(): ?ProductStock
    {
        return $this->stock;
    }

    public function setStock(ProductStock $stock): void
    {
        $this->stock = $stock;
    }

    /** @return Collection<int, ProductImage> */
    public function getImages(): Collection
    {
        return $this->images;
    }

    /** @return Collection<int, ProductMedia> */
    public function getMedia(): Collection
    {
        return $this->media;
    }

    public function addMedium(ProductMedia $medium): void
    {
        if (!$this->media->contains($medium)) {
            $this->media->add($medium);
        }
    }

    /** @return Collection<int, ProductVariant> */
    public function getVariants(): Collection
    {
        return $this->variants;
    }

    public function addVariant(ProductVariant $variant): void
    {
        if (!$this->variants->contains($variant)) {
            $this->variants->add($variant);
        }
    }

    /** @return Collection<int, ProductProperty> */
    public function getProperties(): Collection
    {
        return $this->properties;
    }

    public function addProperty(ProductProperty $property): void
    {
        if (!$this->properties->contains($property)) {
            $this->properties->add($property);
        }
    }

    /** @return Collection<int, ProductCategory> */
    public function getProductCategories(): Collection
    {
        return $this->productCategories;
    }

    public function addProductCategory(ProductCategory $pc): void
    {
        if (!$this->productCategories->contains($pc)) {
            $this->productCategories->add($pc);
        }
    }

    /** @return Collection<int, ProductFilterValue> */
    public function getFilterValues(): Collection
    {
        return $this->filterValues;
    }

    public function addFilterValue(ProductFilterValue $filterValue): void
    {
        if (!$this->filterValues->contains($filterValue)) {
            $this->filterValues->add($filterValue);
        }
    }

    public function removeFilterValue(ProductFilterValue $filterValue): void
    {
        $this->filterValues->removeElement($filterValue);
    }

    public function clearFilterValues(): void
    {
        $this->filterValues->clear();
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
