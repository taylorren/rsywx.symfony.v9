<?php

namespace App\DTO;

class WordOfTheDay
{
    public function __construct(
        public readonly string $word,
        public readonly string $definition,
        public readonly string $pronunciation,
        public readonly string $partOfSpeech,
        public readonly array $examples = [],
        public readonly array $synonyms = [],
        public readonly array $antonyms = [],
        public readonly ?string $etymology = null,
        public readonly ?string $audioUrl = null,
        public readonly ?string $date = null,
        public readonly ?string $source = null
    ) {}

    /**
     * Create WordOfTheDay instance from API response array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            word: $data['word'] ?? '',
            definition: $data['definition'] ?? '',
            pronunciation: $data['pronunciation'] ?? '',
            partOfSpeech: $data['part_of_speech'] ?? '',
            examples: $data['examples'] ?? [],
            synonyms: $data['synonyms'] ?? [],
            antonyms: $data['antonyms'] ?? [],
            etymology: $data['etymology'] ?? null,
            audioUrl: $data['audio_url'] ?? null,
            date: $data['date'] ?? null,
            source: $data['source'] ?? null
        );
    }

    /**
     * Get formatted date
     */
    public function getFormattedDate(): string
    {
        if ($this->date) {
            return date('Y年m月d日', strtotime($this->date));
        }
        return date('Y年m月d日');
    }

    /**
     * Check if word has audio pronunciation
     */
    public function hasAudio(): bool
    {
        return !empty($this->audioUrl);
    }

    /**
     * Check if word has examples
     */
    public function hasExamples(): bool
    {
        return !empty($this->examples);
    }

    /**
     * Check if word has synonyms
     */
    public function hasSynonyms(): bool
    {
        return !empty($this->synonyms);
    }

    /**
     * Check if word has antonyms
     */
    public function hasAntonyms(): bool
    {
        return !empty($this->antonyms);
    }

    /**
     * Check if word has etymology information
     */
    public function hasEtymology(): bool
    {
        return !empty($this->etymology);
    }

    /**
     * Get first example sentence
     */
    public function getFirstExample(): ?string
    {
        return $this->examples[0] ?? null;
    }

    /**
     * Get synonyms as comma-separated string
     */
    public function getSynonymsString(): string
    {
        return implode(', ', $this->synonyms);
    }

    /**
     * Get antonyms as comma-separated string
     */
    public function getAntonymsString(): string
    {
        return implode(', ', $this->antonyms);
    }

    /**
     * Get word summary for display
     */
    public function getSummary(): string
    {
        $parts = [$this->word];
        
        if ($this->pronunciation) {
            $parts[] = '[' . $this->pronunciation . ']';
        }
        
        if ($this->partOfSpeech) {
            $parts[] = '(' . $this->partOfSpeech . ')';
        }
        
        return implode(' ', $parts);
    }

    /**
     * Get truncated definition for preview
     */
    public function getShortDefinition(int $maxLength = 100): string
    {
        if (mb_strlen($this->definition) <= $maxLength) {
            return $this->definition;
        }
        
        return mb_substr($this->definition, 0, $maxLength) . '...';
    }
}