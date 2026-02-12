<?php

namespace App\Logging;

use DateTimeImmutable;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;

class FileLogger extends AbstractLogger
{
    private string $logFile;

    public function __construct(string $logFile)
    {
        $this->logFile = $logFile;

        $directory = dirname($logFile);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create log directory "%s".', $directory));
        }
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $timestamp = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $line = sprintf(
            '[%s] %s: %s%s%s',
            $timestamp,
            strtoupper((string) $level),
            $this->interpolate((string) $message, $context),
            $context ? ' ' . $this->formatContext($context) : '',
            PHP_EOL
        );

        $this->write($line);
    }

    private function interpolate(string $message, array $context): string
    {
        if (empty($context)) {
            return $message;
        }

        $replacements = [];
        foreach ($context as $key => $value) {
            $replacements['{' . $key . '}'] = $this->convertToString($value);
        }

        return strtr($message, $replacements);
    }

    private function formatContext(array $context): string
    {
        $json = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return $json ?: '{}';
    }

    private function write(string $line): void
    {
        if (false === file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX)) {
            throw new RuntimeException(sprintf('Unable to write to log file "%s".', $this->logFile));
        }
    }

    private function convertToString(mixed $value): string
    {
        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $this->jsonEncode($value);
    }

    private function jsonEncode(mixed $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return $json ?: '{}';
    }
}
