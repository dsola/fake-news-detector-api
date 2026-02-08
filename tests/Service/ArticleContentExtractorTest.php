<?php

namespace App\Tests\Service;

use App\Service\ArticleContentExtractor;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class ArticleContentExtractorTest extends KernelTestCase
{
    public function testContentWasSuccessfullyDownloaded(): void
    {
        $htmlContent = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Test Article</title>
    <script>console.log('test');</script>
    <style>body { margin: 0; }</style>
</head>
<body>
    <h1>Test Article Heading</h1>
    <p>This is the main content of the article.</p>
    <p>It has multiple paragraphs.</p>
</body>
</html>
HTML;

        $mockResponse = new MockResponse($htmlContent, ['http_code' => 200]);
        $httpClient = new MockHttpClient($mockResponse);
        $extractor = new ArticleContentExtractor($httpClient);

        $result = $extractor->extractFromUrl('https://example.com/article');

        $this->assertStringContainsString('Test Article Heading', $result);
        $this->assertStringContainsString('This is the main content of the article', $result);
        $this->assertStringContainsString('It has multiple paragraphs', $result);
        $this->assertStringNotContainsString('console.log', $result);
        $this->assertStringNotContainsString('margin: 0', $result);
    }

    public function testErrorOccurredWhileTryingToDownloadArticle(): void
    {
        $mockResponse = new MockResponse('', [
            'http_code' => 500,
            'error' => 'Internal Server Error'
        ]);
        $httpClient = new MockHttpClient($mockResponse);
        $extractor = new ArticleContentExtractor($httpClient);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to download content from URL');

        $extractor->extractFromUrl('https://example.com/broken');
    }

    public function testContentFromUrlSeemsCorrupted(): void
    {
        $corruptedHtml = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title></title>
</head>
<body>
    <script>alert('Only scripts here');</script>
    <style>body { color: red; }</style>
</body>
</html>
HTML;

        $mockResponse = new MockResponse($corruptedHtml, ['http_code' => 200]);
        $httpClient = new MockHttpClient($mockResponse);
        $extractor = new ArticleContentExtractor($httpClient);

        $result = $extractor->extractFromUrl('https://example.com/corrupted');

        // When HTML has only scripts and styles (no meaningful text content), result should be empty
        $this->assertEmpty(trim($result));
    }

    public function testTransportExceptionIsHandled(): void
    {
        $mockResponse = new MockResponse();
        $mockResponse = $mockResponse->cancel();
        
        $httpClient = new MockHttpClient(function () {
            throw new class('Network error') extends \Exception implements TransportExceptionInterface {};
        });
        $extractor = new ArticleContentExtractor($httpClient);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to download content from URL');

        $extractor->extractFromUrl('https://example.com/network-error');
    }
}
