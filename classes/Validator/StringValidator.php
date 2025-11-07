<?php
declare(strict_types=1);
namespace UOPF\Validator;

use UOPF\Validator;
use UOPF\Exception\ValidatorException;
use UOPF\Exception\ValidationException;

class StringValidator extends Validator {
    public function __construct(
        public readonly ?bool $allowEmpty = null,
        public readonly ?int $max = null,
        public readonly ?int $min = null,
        public readonly ?string $format = null,
        public readonly ?string $regex = null
    ) {}

    public function filter(mixed $value): string {
        if (!is_scalar($value))
            throw new ValidationException('Value cannot be converted to a string.');

        if (!is_string($value))
            $value = strval($value);

        if (isset($this->allowEmpty)) {
            if (!$this->allowEmpty) {
                if (strlen($value) <= 0)
                    throw new ValidationException('Value cannot be empty.');
            }
        }

        if (isset($this->max)) {
            if (strlen($value) > $this->max)
                throw new ValidationException("Value length cannot exceed {$this->max}.");
        }

        if (isset($this->min)) {
            if (strlen($value) < $this->min)
                throw new ValidationException("Value length cannot be below {$this->min}.");
        }

        if (isset($this->format)) {
            switch ($this->format) {
                case 'email':
                    if (!static::validateEmail($value))
                        throw new ValidationException('Value must be a valid email address.');

                    break;

                case 'url':
                    if (!static::validateURL($value))
                        throw new ValidationException('Value must be a valid URL.');

                    break;

                default:
                    throw new ValidatorException('Unsupported string format.');
            }
        }

        if (isset($this->regex)) {
            if (!preg_match("/^{$this->regex}$/", $value))
                throw new ValidationException('Value does not meet requirements.');
        }

        return $value;
    }

    protected static function validateEmail(string $value): bool {
        if (strlen($value) < 3)
            return false;

        if (strpos($value, '@', 1) === false)
            return false;

        list($local, $domain) = explode('@', $value, 2);

        if (!preg_match('/^[a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~\.-]+$/', $local))
            return false;

        if (preg_match('/\.{2,}/', $domain))
            return false;

        if (trim($domain, " \t\n\r\0\x0B.") !== $domain)
            return false;

        $subs = explode('.', $domain);

        if (count($subs) < 2)
            return false;

        foreach ($subs as $sub) {
            if (trim($sub, " \t\n\r\0\x0B-") !== $sub)
                return false;

            if (!preg_match('/^[a-z0-9-]+$/i', $sub))
                return false;
        }

        return true;
    }

    protected static function validateURL(string $value): bool {
        return boolval(preg_match('_^(?:(?:https?|ftp)://)(?:\S+(?::\S*)?@)?(?:(?!10(?:\.\d{1,3}){3})(?!127(?:\.\d{1,3}){3})(?!169\.254(?:\.\d{1,3}){2})(?!192\.168(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z\x{00a1}-\x{ffff}0-9]+-?)*[a-z\x{00a1}-\x{ffff}0-9]+)(?:\.(?:[a-z\x{00a1}-\x{ffff}0-9]+-?)*[a-z\x{00a1}-\x{ffff}0-9]+)*(?:\.(?:[a-z\x{00a1}-\x{ffff}]{2,})))(?::\d{2,5})?(?:/[^\s]*)?$_iuS', $value));
    }
}
