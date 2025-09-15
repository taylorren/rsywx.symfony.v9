<?php

namespace App\DTO;

class SearchResult
{
    public function __construct(
        public readonly array $books,
        public readonly int $totalCount,
        public readonly int $currentPage,
        public readonly int $perPage,
        public readonly int $totalPages,
        public readonly string $query,
        public readonly float $searchTime,
        public readonly array $facets = [],
        public readonly array $suggestions = []
    ) {}

    /**
     * Create SearchResult instance from API response array
     */
    public static function fromArray(array $data): self
    {
        $books = [];
        if (isset($data['books']) && is_array($data['books'])) {
            foreach ($data['books'] as $bookData) {
                $books[] = Book::fromArray($bookData);
            }
        }

        return new self(
            books: $books,
            totalCount: $data['total_count'] ?? 0,
            currentPage: $data['current_page'] ?? 1,
            perPage: $data['per_page'] ?? 20,
            totalPages: $data['total_pages'] ?? 1,
            query: $data['query'] ?? '',
            searchTime: (float) ($data['search_time'] ?? 0),
            facets: $data['facets'] ?? [],
            suggestions: $data['suggestions'] ?? []
        );
    }

    /**
     * Check if there are more pages
     */
    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->totalPages;
    }

    /**
     * Check if there is a previous page
     */
    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    /**
     * Get next page number
     */
    public function getNextPage(): ?int
    {
        return $this->hasNextPage() ? $this->currentPage + 1 : null;
    }

    /**
     * Get previous page number
     */
    public function getPreviousPage(): ?int
    {
        return $this->hasPreviousPage() ? $this->currentPage - 1 : null;
    }

    /**
     * Get formatted search time
     */
    public function getFormattedSearchTime(): string
    {
        return number_format($this->searchTime * 1000, 2) . 'ms';
    }

    /**
     * Get result range text (e.g., "1-20 of 150")
     */
    public function getResultRange(): string
    {
        if ($this->totalCount === 0) {
            return '0 results';
        }

        $start = ($this->currentPage - 1) * $this->perPage + 1;
        $end = min($this->currentPage * $this->perPage, $this->totalCount);

        return "{$start}-{$end} of {$this->totalCount}";
    }

    /**
     * Check if search has results
     */
    public function hasResults(): bool
    {
        return $this->totalCount > 0;
    }

    /**
     * Get pagination info for display
     */
    public function getPaginationInfo(): array
    {
        return [
            'current' => $this->currentPage,
            'total' => $this->totalPages,
            'has_previous' => $this->hasPreviousPage(),
            'has_next' => $this->hasNextPage(),
            'previous' => $this->getPreviousPage(),
            'next' => $this->getNextPage(),
            'range' => $this->getResultRange()
        ];
    }

    /**
     * Get books as array (for easier template iteration)
     */
    public function getBooksArray(): array
    {
        return $this->books;
    }

    /**
     * Check if there are search suggestions
     */
    public function hasSuggestions(): bool
    {
        return !empty($this->suggestions);
    }

    /**
     * Check if there are facets for filtering
     */
    public function hasFacets(): bool
    {
        return !empty($this->facets);
    }
}