<?php

declare(strict_types=1);

namespace App\Service;

class RelevantWordsExtractor
{
    /**
     * @var array<string, list<string>>
     */
    private readonly array $stopWordsByLanguage;

    /**
     * @var string
     */
    private string $language = 'en';

    public function __construct()
    {
        $configPath = __DIR__ . '/../../config/stopwords-iso.php';

        if (! is_file($configPath)) {
            throw new \RuntimeException(sprintf(
                'Stop words configuration file not found at path "%s".',
                $configPath
            ));
        }

        /** @var mixed $config */
        $config = require $configPath;

        if (! is_array($config)) {
            throw new \RuntimeException('Stop words configuration must return an array.');
        }

        $this->stopWordsByLanguage = $this->normalizeConfig($config);
    }

    /**
     * Set the current language (ISO 639-1 code).
     *
     * @throws \InvalidArgumentException if language is not configured.
     */
    public function setLanguage(string $language): void
    {
        $language = strtolower($language);

        if (! array_key_exists($language, $this->stopWordsByLanguage)) {
            throw new \InvalidArgumentException(sprintf(
                'Language "%s" is not configured for stop words.',
                $language
            ));
        }

        $this->language = $language;
    }

    /**
     * Extract the most relevant words from the given text.
     *
     * @param string $text      Input text.
     * @param int    $maxWords  Maximum number of words to return (0 = no limit).
     * @param int    $minLength Minimum length of a word to be kept.
     *
     * @return list<string> Relevant words sorted by descending frequency.
     */
    public function extract(string $text, int $maxWords = 10, int $minLength = 3): array
    {
        if (trim($text) === '') {
            return [];
        }

        $normalized = mb_strtolower($this->normalizeText($text), 'UTF-8');

        preg_match_all('/[\p{L}\p{N}]+/u', $normalized, $matches);
        $tokens = $matches[0] ?? [];

        $stopWords = $this->stopWordsByLanguage[$this->language] ?? [];

        $filtered = array_values(array_filter(
            $tokens,
            function (string $token) use ($minLength, $stopWords): bool {
                if (mb_strlen($token) < $minLength) {
                    return false;
                }

                if (in_array($token, $stopWords, true)) {
                    return false;
                }

                if (preg_match('/^\d+$/u', $token) === 1) {
                    return false;
                }

                return true;
            }
        ));

        if ($filtered === []) {
            return [];
        }

        $frequencies = array_count_values($filtered);

        uasort(
            $frequencies,
            static function (int $countA, int $countB): int {
                return $countB <=> $countA;
            }
        );

        $orderedWords = array_keys($frequencies);

        if ($maxWords > 0) {
            $orderedWords = array_slice($orderedWords, 0, $maxWords);
        }

        return array_values($orderedWords);
    }

    private function normalizeText(string $text): string
    {
        $cleaned = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);

        if ($cleaned === null) {
            return $text;
        }

        $cleaned = preg_replace('/\s+/u', ' ', $cleaned);

        return trim($cleaned);
    }

    /**
     * @param array<string, list<string>> $config
     * @return array<string, list<string>>
     */
    private function normalizeConfig(array $config): array
    {
        $normalized = [];

        foreach ($config as $language => $words) {
            if (! is_array($words)) {
                continue;
            }

            $langKey = strtolower((string) $language);
            $normalized[$langKey] = $this->normalizeStopWords($words);
        }

        return $normalized;
    }

    /**
     * @param list<string> $stopWords
     * @return list<string>
     */
    private function normalizeStopWords(array $stopWords): array
    {
        $normalized = array_map(
            static fn (string $word): string => mb_strtolower(trim($word)),
            $stopWords
        );

        $normalized = array_filter(
            $normalized,
            static fn (string $word): bool => $word !== ''
        );

        return array_values(array_unique($normalized));
    }
}
