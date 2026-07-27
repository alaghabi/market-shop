<?php

namespace App\Dto\Review;

final class ReviewOutput
{
    public string $id;
    public ?string $boutiqueId;
    public ?string $boutiqueName = null;
    public ?string $productId;
    public ?string $productName = null;
    public ?string $categoryId = null;
    public ?string $categoryName = null;
    public string $targetType = 'general';
    public ?string $userId;
    public string $authorName;
    public ?string $authorEmail;
    public ?string $authorPhone;
    public int $rating;
    public ?string $title;
    public ?string $comment;
    /** @var list<string> */
    public array $images = [];
    public bool $isVerifiedPurchase;
    public string $status;
    public \DateTimeImmutable $createdAt;
}
