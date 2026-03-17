<?php

namespace App\Service;

use NlpTools\Similarity\CosineSimilarity;

class SimilarityAnalyzer
{
    private CosineSimilarity $cosine;

    public function __construct()
    {
        $this->cosine = new CosineSimilarity();
    }

    /**
     * Compare two texts using Cosine Similarity with TF-IDF tokenization
     *
     * @param string $originalText The original article text
     * @param string $candidateText The candidate article text to compare
     * @return float Similarity score between 0 and 1 (0 = completely different, 1 = identical)
     */
    public function compare(string $originalText, string $candidateText): float
    {
        // Normalize texts for better comparison
        $originalText = $this->normalize($originalText);
        $candidateText = $this->normalize($candidateText);

        // Tokenize both texts
        $tokensA = $this->tokenize($originalText);
        $tokensB = $this->tokenize($candidateText);

        // If either text is empty, return 0 similarity
        if (empty($tokensA) || empty($tokensB)) {
            return 0.0;
        }

        // Create term frequency vectors
        $setA = array_count_values($tokensA);
        $setB = array_count_values($tokensB);

        // Calculate and return cosine similarity
        return $this->cosine->similarity($setA, $setB);
    }

    /**
     * Normalize text for comparison by removing punctuation and standardizing whitespace
     *
     * @param string $text The text to normalize
     * @return string The normalized text
     */
    private function normalize(string $text): string
    {
        // Convert to lowercase
        $text = strtolower($text);
        
        // Remove punctuation except spaces (keep word characters and spaces)
        $text = preg_replace('/[^\w\s]/', ' ', $text);
        
        // Normalize multiple spaces to single space
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Trim whitespace from beginning and end
        return trim($text);
    }

    /**
     * Tokenize text by splitting on whitespace
     *
     * @param string $text The text to tokenize
     * @return array<string> Array of tokens
     */
    private function tokenize(string $text): array
    {
        if (empty($text)) {
            return [];
        }
        
        // Split on whitespace without using deprecated preg_split with null limit
        return array_filter(explode(' ', $text), fn($token) => !empty($token));
    }

    /**
     * Compare multiple candidate texts against an original text
     *
     * @param string $originalText The original article text
     * @param array<string> $candidateTexts Array of candidate texts to compare
     * @return array<int, float> Array of similarity scores indexed by candidate position
     */
    public function compareMultiple(string $originalText, array $candidateTexts): array
    {
        $scores = [];

        foreach ($candidateTexts as $index => $candidateText) {
            $scores[$index] = $this->compare($originalText, $candidateText);
        }

        return $scores;
    }
}
