<?php

namespace App\Service;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class ArticleContentExtractor
{
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
            $response = $this->httpClient->request('GET', $url);
            return $response->getContent();
        } catch (TransportExceptionInterface|ClientExceptionInterface|ServerExceptionInterface|RedirectExceptionInterface $e) {
            throw new \RuntimeException(
                sprintf('Failed to download content from URL: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Extract text content from HTML
     */
    private function extractTextFromHtml(string $html): string
    {
        try {
            $crawler = new Crawler($html);
            
            // Remove script and style tags
            $crawler->filterXpath('//script | //style')->each(function (Crawler $node) {
                if ($node->getNode(0)->parentNode !== null) {
                    $node->getNode(0)->parentNode->removeChild($node->getNode(0));
                }
            });

            // Extract text
            $text = $crawler->text();
            
            return trim($text);
        } catch (\Exception $e) {
            return '';
        }
    }
}
