<?php
declare(strict_types=1);
namespace UOPF\Interface;

use Throwable;
use UOPF\Response;

/**
 * API Exception
 */
final class Exception extends \Exception {
    public function __construct(
        string $message = '',
        int $code = 0,

        /**
         * Extra data to be sent to the client.
         */
        public readonly array $data = [],

        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function renderTo(Response $response): void {
        $content = $this->data;
        $content['message'] = $this->getMessage();

        $response->setStatusCode($this->getCode());
        $response->setContent(json_encode($content));
    }
}
