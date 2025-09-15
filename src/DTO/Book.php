<?php

namespace App\DTO;

class Book
{
    public function __construct(
        public readonly int $id,
        public readonly string $bookid,
        public readonly string $title,
        public readonly string $author,
        public readonly bool $translated,
        public readonly ?string $copyrighter,
        public readonly string $region,
        public readonly string $location,
        public readonly string $purchdate,
        public readonly float $price,
        public readonly ?string $pubdate = null,
        public readonly ?string $printdate = null,
        public readonly ?string $ver = null,
        public readonly ?string $deco = null,
        public readonly ?string $isbn = null,
        public readonly ?string $category = null,
        public readonly ?string $ol = null,
        public readonly ?int $kword = null,
        public readonly ?int $page = null,
        public readonly ?string $intro = null,
        public readonly ?bool $instock = null,
        public readonly ?string $publisherName = null,
        public readonly ?string $placeName = null,
        public readonly array $tags = [],
        public readonly array $reviews = [],
        public readonly ?string $coverUri = null,
        public readonly ?int $totalVisits = null,
        public readonly ?string $lastVisited = null,
        public readonly ?string $visitCountry = null,
        public readonly ?int $daysSinceVisit = null,
        public readonly ?int $yearsAgo = null
    ) {}

    /**
     * Create Book instance from API response array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            bookid: $data['bookid'],
            title: $data['title'],
            author: $data['author'],
            translated: $data['translated'],
            copyrighter: $data['copyrighter'] ?? null,
            region: $data['region'],
            location: $data['location'],
            purchdate: $data['purchdate'],
            price: (float) $data['price'],
            pubdate: $data['pubdate'] ?? null,
            printdate: $data['printdate'] ?? null,
            ver: $data['ver'] ?? null,
            deco: $data['deco'] ?? null,
            isbn: $data['isbn'] ?? null,
            category: $data['category'] ?? null,
            ol: $data['ol'] ?? null,
            kword: $data['kword'] ?? null,
            page: $data['page'] ?? null,
            intro: $data['intro'] ?? null,
            instock: $data['instock'] ?? null,
            publisherName: $data['publisher_name'] ?? null,
            placeName: $data['place_name'] ?? null,
            tags: $data['tags'] ?? [],
            reviews: $data['reviews'] ?? [],
            coverUri: $data['cover_uri'] ?? null,
            totalVisits: $data['total_visits'] ?? null,
            lastVisited: $data['last_visited'] ?? null,
            visitCountry: $data['visit_country'] ?? null,
            daysSinceVisit: $data['days_since_visit'] ?? null,
            yearsAgo: $data['years_ago'] ?? null
        );
    }

    /**
     * Get formatted price with currency
     */
    public function getFormattedPrice(): string
    {
        return '¥' . number_format($this->price, 2);
    }

    /**
     * Get formatted purchase date
     */
    public function getFormattedPurchaseDate(): string
    {
        return date('Y年m月d日', strtotime($this->purchdate));
    }

    /**
     * Check if book has cover image
     */
    public function hasCover(): bool
    {
        return !empty($this->coverUri);
    }

    /**
     * Get book summary for display
     */
    public function getSummary(): string
    {
        $parts = [];
        
        if ($this->translated) {
            $parts[] = '[译]';
        }
        
        $parts[] = $this->title;
        $parts[] = '- ' . $this->author;
        
        if ($this->region !== '中国') {
            $parts[] = '(' . $this->region . ')';
        }
        
        return implode(' ', $parts);
    }

    /**
     * Get tags as comma-separated string
     */
    public function getTagsString(): string
    {
        return implode(', ', $this->tags);
    }
}