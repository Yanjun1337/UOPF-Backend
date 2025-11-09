<?php
declare(strict_types=1);
namespace UOPF;

use Generator;

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

    public static function eachText(string $html): Generator {
        foreach (preg_split(static::getHTMLSeparator(), $html) as $block)
            yield static::unescape($block);
    }

    protected static function getHTMLSeparator(): string {
        $opening = '(?:\s*(?:[A-z][A-z0-9]*)[^>]*';
        $closing = '\s*\/\s*(?:[A-z][A-z0-9]*)\s*)';
        return "/(<(?:{$opening})|(?:{$closing})>)/";
    }
}
