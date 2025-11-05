<?php
declare(strict_types=1);
namespace UOPF;

/**
 * Utilities
 */
abstract class Utilities {
    public static function escape(string $string): string {
        return htmlspecialchars($string, encoding: 'UTF-8');
    }

    public static function wrapParagraphsAround(string $text): string {
		$replaced = str_replace("\r\n", "\n", $text);
		$split = preg_split('/\n+/', $replaced);

        $rendered = implode("</p>\n<p>", $split);
        return "<p>{$rendered}</p>";
    }
}
