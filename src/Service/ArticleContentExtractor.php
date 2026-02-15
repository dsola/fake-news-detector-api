<?php

namespace App\Service;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class ArticleContentExtractor
{
    // Semantic HTML5 selectors for main content
    private const CONTENT_SELECTORS = [
        'article',
        '[role="main"]',
        'main',
        '.article-body',
        '.article-content',
        '.post-content',
        '.entry-content',
        '#article-body',
        '#main-content',
    ];

    // Elements to remove (boilerplate/clutter)
    private const REMOVE_SELECTORS = [
        'script',
        'style',
        'nav',
        'header',
        'footer',
        'aside',
        '.sidebar',
        '.advertisement',
        '.ad',
        '.comments',
        '.related-articles',
        '.social-share',
        '.newsletter',
        '[role="complementary"]',
        '[role="navigation"]',
        '[aria-label*="advertisement"]',
        '.menu',
        '.breadcrumb',
        '.tags',
        '.meta',
    ];

    public function __construct(private readonly HttpClientInterface $httpClient) {}

    /**
     * Download and extract text content from a URL
     * 
     * @throws \RuntimeException
     */
    public function extractFromUrl(string $url): string
    {
        $htmlContent = $this->downloadHtmlContent($url);
        return $this->extractTextFromHtml($htmlContent);
    }

    /**
     * Download HTML content from the given URL
     * 
     * @throws \RuntimeException
     */
    private function downloadHtmlContent(string $url): string
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ],
            ]);
            return $response->getContent();
        } catch (TransportExceptionInterface|ClientExceptionInterface|ServerExceptionInterface $e) {
            throw new \RuntimeException(
                sprintf('Failed to download content from URL: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Extract text content from HTML, focusing on main article content
     */
    private function extractTextFromHtml(string $html): string
    {
        try {
            $crawler = new Crawler($html);
            
            // Try to find the main content area using semantic selectors
            $contentCrawler = $this->findMainContent($crawler);
            
            if ($contentCrawler === null || $contentCrawler->count() === 0) {
                // Fallback: use the whole body but remove unwanted elements
                $contentCrawler = $crawler->filter('body');
            }
            
            // Remove unwanted elements from the content
            $this->removeUnwantedElements($contentCrawler);
            
            // Extract and clean text
            $text = $contentCrawler->text();
            
            return $this->cleanText($text);
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Find the main content area using semantic HTML5 selectors
     */
    private function findMainContent(Crawler $crawler): ?Crawler
    {
        foreach (self::CONTENT_SELECTORS as $selector) {
            try {
                $content = $crawler->filter($selector);
                if ($content->count() > 0) {
                    return $content->first();
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        return null;
    }

    /**
     * Remove unwanted elements (navigation, ads, etc.)
     */
    private function removeUnwantedElements(Crawler $crawler): void
    {
        foreach (self::REMOVE_SELECTORS as $selector) {
            try {
                $crawler->filter($selector)->each(function (Crawler $node) {
                    $domNode = $node->getNode(0);
                    if ($domNode && $domNode->parentNode !== null) {
                        $domNode->parentNode->removeChild($domNode);
                    }
                });
            } catch (\Exception $e) {
                // Selector might not exist, continue
                continue;
            }
        }
    }

    /**
     * Clean extracted text (remove extra whitespace, normalize)
     */
    private function cleanText(string $text): string
    {
        // Replace multiple spaces with single space
        $text = preg_replace('/\h+/', ' ', $text);
        
        // Replace multiple newlines with double newline (paragraph breaks)
        $text = preg_replace('/\v+/', "\n\n", $text);
        
        // Remove leading/trailing whitespace
        $text = trim($text);
        
        return $text;
    }
}
