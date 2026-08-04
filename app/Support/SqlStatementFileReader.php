<?php

declare(strict_types=1);

namespace App\Support;

use Generator;
use RuntimeException;

class SqlStatementFileReader
{
    public static function statements(string $path): Generator
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open SQL file: {$path}");
        }

        $buffer = '';
        $quote = null;
        $inLineComment = false;
        $inBlockComment = false;

        try {
            while (($line = fgets($handle)) !== false) {
                $length = strlen($line);

                for ($i = 0; $i < $length; $i++) {
                    $char = $line[$i];
                    $next = $i + 1 < $length ? $line[$i + 1] : '';

                    if ($inLineComment) {
                        $buffer .= $char;
                        if ($char === "\n") {
                            $inLineComment = false;
                        }
                        continue;
                    }

                    if ($inBlockComment) {
                        $buffer .= $char;
                        if ($char === '*' && $next === '/') {
                            $buffer .= $next;
                            $i++;
                            $inBlockComment = false;
                        }
                        continue;
                    }

                    if ($quote !== null) {
                        $buffer .= $char;
                        if ($char === '\\' && $i + 1 < $length) {
                            $buffer .= $line[$i + 1];
                            $i++;
                            continue;
                        }

                        if ($char === $quote) {
                            $quote = null;
                        }
                        continue;
                    }

                    if ($char === "'" || $char === '"') {
                        $quote = $char;
                        $buffer .= $char;
                        continue;
                    }

                    if ($char === '-' && $next === '-') {
                        $inLineComment = true;
                        $buffer .= $char . $next;
                        $i++;
                        continue;
                    }

                    if ($char === '/' && $next === '*') {
                        $inBlockComment = true;
                        $buffer .= $char . $next;
                        $i++;
                        continue;
                    }

                    if ($char === ';') {
                        $statement = self::normalizeStatement($buffer);
                        if ($statement !== '') {
                            yield $statement;
                        }

                        $buffer = '';
                        continue;
                    }

                    $buffer .= $char;
                }
            }

            $statement = self::normalizeStatement($buffer);
            if ($statement !== '') {
                yield $statement;
            }
        } finally {
            fclose($handle);
        }
    }

    private static function normalizeStatement(string $statement): string
    {
        $trimmed = trim($statement);

        if ($trimmed === '') {
            return '';
        }

        do {
            $previous = $trimmed;
            $trimmed = preg_replace('/\A\s*--[^\r\n]*(?:\r?\n|$)/', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/\A\s*\/\*[\s\S]*?\*\//', '', $trimmed) ?? $trimmed;
            $trimmed = ltrim($trimmed);
        } while ($trimmed !== $previous);

        return trim($trimmed);
    }
}
