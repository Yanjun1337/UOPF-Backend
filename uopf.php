<?php
declare(strict_types=1);
namespace UOPF;

/**
 * The path to working directory of UOPF.
 *
 * @var string
 * @access public
 */
const ROOT = __DIR__ . '/';

// Check whether dependencies are installed.
if (!file_exists(ROOT . 'vendor/autoload.php')) {
    if (in_array(\PHP_SAPI, ['cli', 'phpdbg'], true)) {
        echo "ERROR: Necessary dependencies are not installed.\n";
        exit(1);
    } else {
        if (!headers_sent())
            header('HTTP/1.1 500 Internal Server Error', true, 500);

        if (ini_get('display_errors'))
            die('<h1>ERROR: Necessary dependencies are not installed.</h1>');
        else
            die('<h1>ERROR: Internal error occurred.</h1>');
    }
}

// Load autoloaders and libraries.
require( ROOT . 'vendor/autoload.php' );

// Set up runtime environment.
Services::configureEnvironment();
