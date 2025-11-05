<?php
declare(strict_types=1);
namespace UOPF\Exception;

use Throwable;
use UOPF\Exception;

/**
 * Captcha Exception
 */
final class CaptchaException extends Exception {
    public function __construct(
        /**
         * The response from hCaptcha.
         */
        public readonly array $response,

        string $message = '',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
