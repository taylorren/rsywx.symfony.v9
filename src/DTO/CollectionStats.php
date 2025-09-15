<?php

namespace App\DTO;

class CollectionStats
{
    public function __construct(
        public readonly int $totalBooks,
        public readonly int $totalAuthors,
        public readonly int $totalPublishers,
        public readonly int $totalCountries,
        public readonly float $totalValue,
        public readonly int $booksThisYear,
        public readonly float $valueThisYear,
        public readonly int $booksThisMonth,
        public readonly float $valueThisMonth,
        public readonly array $topAuthors = [],
        public readonly array $topPublishers = [],
        public readonly array $topCountries = [],
        public readonly array $recentPurchases = [],
        public readonly ?string $lastUpdated = null
    ) {}

    /**
     * Create CollectionStats instance from API response array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            totalBooks: $data['total_books'] ?? 0,
            totalAuthors: $data['total_authors'] ?? 0,
            totalPublishers: $data['total_publishers'] ?? 0,
            totalCountries: $data['total_countries'] ?? 0,
            totalValue: (float) ($data['total_value'] ?? 0),
            booksThisYear: $data['books_this_year'] ?? 0,
            valueThisYear: (float) ($data['value_this_year'] ?? 0),
            booksThisMonth: $data['books_this_month'] ?? 0,
            valueThisMonth: (float) ($data['value_this_month'] ?? 0),
            topAuthors: $data['top_authors'] ?? [],
            topPublishers: $data['top_publishers'] ?? [],
            topCountries: $data['top_countries'] ?? [],
            recentPurchases: $data['recent_purchases'] ?? [],
            lastUpdated: $data['last_updated'] ?? null
        );
    }

    /**
     * Get formatted total value with currency
     */
    public function getFormattedTotalValue(): string
    {
        return '¥' . number_format($this->totalValue, 2);
    }

    /**
     * Get formatted value for this year
     */
    public function getFormattedYearValue(): string
    {
        return '¥' . number_format($this->valueThisYear, 2);
    }

    /**
     * Get formatted value for this month
     */
    public function getFormattedMonthValue(): string
    {
        return '¥' . number_format($this->valueThisMonth, 2);
    }

    /**
     * Get average book price
     */
    public function getAverageBookPrice(): float
    {
        return $this->totalBooks > 0 ? $this->totalValue / $this->totalBooks : 0;
    }

    /**
     * Get formatted average book price
     */
    public function getFormattedAveragePrice(): string
    {
        return '¥' . number_format($this->getAverageBookPrice(), 2);
    }

    /**
     * Get books per author ratio
     */
    public function getBooksPerAuthor(): float
    {
        return $this->totalAuthors > 0 ? $this->totalBooks / $this->totalAuthors : 0;
    }

    /**
     * Get percentage of books purchased this year
     */
    public function getYearPurchasePercentage(): float
    {
        return $this->totalBooks > 0 ? ($this->booksThisYear / $this->totalBooks) * 100 : 0;
    }

    /**
     * Get formatted percentage of books purchased this year
     */
    public function getFormattedYearPercentage(): string
    {
        return number_format($this->getYearPurchasePercentage(), 1) . '%';
    }

    /**
     * Check if collection is growing (books purchased this month > 0)
     */
    public function isGrowing(): bool
    {
        return $this->booksThisMonth > 0;
    }

    /**
     * Get collection diversity score (countries per 100 books)
     */
    public function getDiversityScore(): float
    {
        return $this->totalBooks > 0 ? ($this->totalCountries / $this->totalBooks) * 100 : 0;
    }
}