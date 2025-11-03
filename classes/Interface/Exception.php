<?php
declare(strict_types=1);
namespace UOPF\Interface;

use UOPF\Response;

/**
 * API Exception
 */
final class Exception extends \Exception {
    public function renderTo(Response $response): void {
        $response->setStatusCode($this->getCode());

        $response->setContent(json_encode([
            'message' => $this->getMessage()
        ]));
    }
}
