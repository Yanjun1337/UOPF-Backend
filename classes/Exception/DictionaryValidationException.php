<?php
declare(strict_types=1);
namespace UOPF\Exception;

use Throwable;

final class DictionaryValidationException extends ValidationException {
    public function __construct(
        /**
         * The key of the element that caused this exception.
         */
        public readonly int|string $elementKey,

        /**
         * The label of the element that caused this exception.
         */
        public readonly string $elementLabel,

        string $message = '',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getLabeledMessage(): string {
        $message = $this->getMessage();
        return "{$this->elementLabel}: {$message}";
    }
}
