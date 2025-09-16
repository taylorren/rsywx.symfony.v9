<?php

namespace App\DTO;

class WordOfTheDay
{
    public function __construct(
        public readonly int $id,
        public readonly string $word,
        public readonly string $meaning,
        public readonly string $sentence,
        public readonly string $type
    ) {}

    /**
     * Create WordOfTheDay instance from API response array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? 0,
            word: $data['word'] ?? '',
            meaning: $data['meaning'] ?? '',
            sentence: $data['sentence'] ?? '',
            type: $data['type'] ?? ''
        );
    }

    /**
     * Get first example sentence
     */
    public function getFirstExample(): ?string
    {
        return $this->sentence;
    }

    /**
     * Get synonyms as comma-separated string (not available in API)
     */
    public function getSynonymsString(): string
    {
        return '';
    }

    /**
     * Get antonyms as comma-separated string (not available in API)
     */
    public function getAntonymsString(): string
    {
        return '';
    }

    /**
     * Check if word has synonyms (not available in API)
     */
    public function hasSynonyms(): bool
    {
        return false;
    }

    /**
     * Check if word has antonyms (not available in API)
     */
    public function hasAntonyms(): bool
    {
        return false;
    }

    /**
     * Check if word has etymology information (not available in API)
     */
    public function hasEtymology(): bool
    {
        return false;
    }

    /**
     * Check if word has examples
     */
    public function hasExamples(): bool
    {
        return !empty($this->sentence);
    }

    /**
     * Check if word has audio pronunciation (not available in API)
     */
    public function hasAudio(): bool
    {
        return false;
    }

    /**
     * Get word summary for display
     */
    public function getSummary(): string
    {
        $parts = [$this->word];
        
        if ($this->type) {
            $parts[] = '(' . $this->type . ')';
        }
        
        return implode(' ', $parts);
    }

    /**
     * Get truncated definition for preview
     */
    public function getShortDefinition(int $maxLength = 100): string
    {
        if (mb_strlen($this->meaning) <= $maxLength) {
            return $this->meaning;
        }
        
        return mb_substr($this->meaning, 0, $maxLength) . '...';
    }
}