<?php

namespace App\DTO;

class QuoteOfTheDay
{
    public function __construct(
        public readonly int $id,
        public readonly string $quote,
        public readonly string $source
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            quote: $data['quote'],
            source: $data['source']
        );
    }

    /**
     * Get quote content
     */
    public function getContent(): string
    {
        return $this->quote;
    }

    /**
     * Get quote author/source
     */
    public function getAuthor(): string
    {
        return $this->source;
    }

    /**
     * Get truncated quote for preview
     */
    public function getShortQuote(int $maxLength = 100): string
    {
        if (mb_strlen($this->quote) <= $maxLength) {
            return $this->quote;
        }
        
        return mb_substr($this->quote, 0, $maxLength) . '...';
    }

    /**
     * Check if quote has tags (not available in API)
     */
    public function hasTags(): bool
    {
        return false;
    }

    /**
     * Get tags as comma-separated string (not available in API)
     */
    public function getTagsString(): string
    {
        return '';
    }

    /**
     * Check if quote has category (not available in API)
     */
    public function hasCategory(): bool
    {
        return false;
    }

    /**
     * Get quote summary for display
     */
    public function getSummary(): string
    {
        $shortQuote = $this->getShortQuote(50);
        return $shortQuote . ' - ' . $this->source;
    }
}