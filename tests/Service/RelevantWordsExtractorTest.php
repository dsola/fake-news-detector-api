<?php

declare(strict_types=1);

namespace App\Tests\Text;

use App\Service\RelevantWordsExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RelevantWordsExtractorTest extends TestCase
{
    public function testEmptyTextReturnsEmptyArray(): void
    {
        $extractor = new RelevantWordsExtractor();

        $result = $extractor->extract('');

        $this->assertSame([], $result);
    }

    public function testNormalizesSpecialCharactersBeforeTokenizing(): void
    {
        $extractor = new RelevantWordsExtractor();

        $result = $extractor->extract('Hello—world!!!', maxWords: 0);

        $this->assertEqualsCanonicalizing(['hello', 'world'], $result);
    }

    public function testSettingUnsupportedLanguageThrowsException(): void
    {
        $extractor = new RelevantWordsExtractor();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Language "xx" is not configured');

        $extractor->setLanguage('xx');
    }

    #[DataProvider('languageExamplesProvider')]
    public function testExtractRelevantWordsForSupportedLanguages(
        string $language,
        string $text,
        array $expectedContains
    ): void {
        $extractor = new RelevantWordsExtractor();
        $extractor->setLanguage($language);

        $result = $extractor->extract($text, maxWords: 10);

        foreach ($expectedContains as $expectedWord) {
            $this->assertContains(
                $expectedWord,
                $result,
                sprintf(
                    'Failed asserting that "%s" is contained in extracted keywords for language "%s".',
                    $expectedWord,
                    $language
                )
            );
        }
    }

    public static function languageExamplesProvider(): array
    {
        return [
            'english_example' => [
                'en',
                'PHP 8.3 introduces new features and best practices for modern web development.',
                ['php', 'introduces', 'features', 'development'],
            ],
            'spanish_example' => [
                'es',
                'PHP 8.3 introduce nuevas características para el desarrollo web moderno.',
                ['php', 'introduce', 'nuevas', 'características', 'desarrollo'],
            ],
            'dutch_example' => [
                'nl',
                'PHP 8.3 biedt nieuwe mogelijkheden voor moderne webapplicaties.',
                ['php', 'biedt', 'nieuwe', 'mogelijkheden', 'webapplicaties'],
            ],
        ];
    }
}
