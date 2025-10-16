<?php
declare(strict_types=1);

// Load UOPF.
require( __DIR__ . '/uopf.php' );

// Handle incoming request.
UOPF\Services::serveRequest();
