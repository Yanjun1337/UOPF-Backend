<?php
declare(strict_types=1);
namespace UOPF;

use Generator;
use const PREG_SPLIT_DELIM_CAPTURE;

/**
 * Utilities
 */
abstract class Utilities {
    public static function escape(string $html): string {
        return htmlspecialchars($html, encoding: 'UTF-8');
    }

    public static function unescape(string $text): string {
        return html_entity_decode($text, encoding: 'UTF-8');
    }

    public static function wrapParagraphsAround(string $text): string {
		$replaced = str_replace("\r\n", "\n", $text);
		$split = preg_split('/\n+/', $replaced);

        $rendered = implode("</p>\n<p>", $split);
        return "<p>{$rendered}</p>";
    }

    public static function textEach(string $html): Generator {
        foreach (preg_split(static::getHTMLSeparator(), $html) as $block)
            yield static::unescape($block);
    }

    public static function textMap(callable $callback, string $html): string {
        $separator = static::getHTMLSeparator();
        $split = preg_split($separator, $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($split as $index => $block)
            if (preg_match($separator, $block) <= 0)
                $split[$index] = $callback($block);

        return implode('', $split);
    }

    /**
     * Compared to `array_column()`, this method adds support for objects that implement
     * `ArrayAccess`.
     */
    public static function arrayColumn(array $array, int|string $column): array {
        $rows = [];

        foreach ($array as $value)
            $rows[] = $value[$column];

        return $rows;
    }

    protected static function getHTMLSeparator(): string {
        $opening = '(?:\s*(?:[A-z][A-z0-9]*)[^>]*';
        $closing = '\s*\/\s*(?:[A-z][A-z0-9]*)\s*)';
        return "/(<(?:{$opening})|(?:{$closing})>)/";
    }
}
