<?php

namespace App\Tests\Service;

use App\Service\SimilarityAnalyzer;
use PHPUnit\Framework\TestCase;

class SimilarityAnalyzerTest extends TestCase
{
    private SimilarityAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new SimilarityAnalyzer();
    }

    public function testCompareIdenticalTexts(): void
    {
        $text = 'This is a sample article about technology and innovation';
        
        $score = $this->analyzer->compare($text, $text);
        
        $this->assertEqualsWithDelta(1.0, $score, 0.0001, 'Identical texts should have a similarity score of ~1.0');
    }

    public function testCompareSimilarTexts(): void
    {
        $text1 = 'Breaking news about climate change and global warming';
        $text2 = 'Latest updates on climate change and environmental issues';
        
        $score = $this->analyzer->compare($text1, $text2);
        
        $this->assertGreaterThan(0.0, $score, 'Similar texts should have a positive similarity score');
        $this->assertLessThan(1.0, $score, 'Non-identical texts should have a score less than 1.0');
    }

    public function testCompareCompletelyDifferentTexts(): void
    {
        $text1 = 'This article is about sports and athletics';
        $text2 = 'A story covering financial markets and economics';
        
        $score = $this->analyzer->compare($text1, $text2);
        
        $this->assertLessThan(0.5, $score, 'Completely different texts should have a low similarity score');
    }

    public function testCompareEmptyStrings(): void
    {
        $score = $this->analyzer->compare('', '');
        
        $this->assertEquals(0.0, $score, 'Empty strings should have a similarity score of 0.0');
    }

    public function testCompareOneEmptyString(): void
    {
        $text = 'This is some content';
        
        $score1 = $this->analyzer->compare($text, '');
        $score2 = $this->analyzer->compare('', $text);
        
        $this->assertEquals(0.0, $score1, 'Comparison with empty string should return 0.0');
        $this->assertEquals(0.0, $score2, 'Comparison with empty string should return 0.0');
    }

    public function testCompareCaseInsensitive(): void
    {
        $text1 = 'This Is A Test Article';
        $text2 = 'this is a test article';
        
        $score = $this->analyzer->compare($text1, $text2);
        
        // Use delta comparison due to floating-point precision
        $this->assertEqualsWithDelta(1.0, $score, 0.0001, 'Comparison should be case-insensitive');
    }

    public function testCompareMultiple(): void
    {
        $originalText = 'Climate change is affecting global weather patterns';
        $candidates = [
            'Climate change impacts weather worldwide',
            'Technology advances in artificial intelligence',
            'Weather patterns shift due to climate change',
            'Sports news and athletic competitions',
        ];
        
        $scores = $this->analyzer->compareMultiple($originalText, $candidates);
        
        $this->assertCount(4, $scores, 'Should return scores for all candidates');
        $this->assertArrayHasKey(0, $scores);
        $this->assertArrayHasKey(1, $scores);
        $this->assertArrayHasKey(2, $scores);
        $this->assertArrayHasKey(3, $scores);
        
        // First and third candidates should have higher scores (more similar)
        $this->assertGreaterThan($scores[1], $scores[0], 'Similar text should have higher score');
        $this->assertGreaterThan($scores[3], $scores[2], 'Similar text should have higher score');
    }

    public function testCompareReturnsFloatBetweenZeroAndOne(): void
    {
        $text1 = 'Some random article content here';
        $text2 = 'Different article with some overlap content';
        
        $score = $this->analyzer->compare($text1, $text2);
        
        $this->assertIsFloat($score, 'Score should be a float');
        $this->assertGreaterThanOrEqual(0.0, $score, 'Score should be >= 0.0');
        $this->assertLessThanOrEqual(1.0, $score, 'Score should be <= 1.0');
    }

    public function testCompareWithSpecialCharacters(): void
    {
        $text1 = 'Article about climate-change & global warming!';
        $text2 = 'Article about climate change and global warming';
        
        $score = $this->analyzer->compare($text1, $text2);
        
        // With normalization, punctuation is removed, so similarity should be high
        // 'climate-change' becomes 'climate change', making texts very similar
        $this->assertGreaterThan(0.8, $score, 'Texts should have high similarity after punctuation normalization');
    }

    public function testNormalizationHandlesPunctuation(): void
    {
        $text1 = "Hello, world! This is a test message";
        $text2 = "Hello world This is a test message";
        
        $score = $this->analyzer->compare($text1, $text2);
        
        // After normalization, both texts should be nearly identical
        // (commas and exclamation marks are removed)
        $this->assertEqualsWithDelta(1.0, $score, 0.0001, 'Punctuation should be normalized away');
    }
}
