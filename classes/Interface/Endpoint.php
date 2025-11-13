<?php
declare(strict_types=1);
namespace UOPF\Interface;

use ReflectionClass;
use UOPF\Model;
use UOPF\Captcha;
use UOPF\Request;
use UOPF\Response;
use UOPF\Services;
use UOPF\Exception as UOPFException;
use UOPF\Model\User as UserModel;
use UOPF\Model\TheCase as CaseModel;
use UOPF\Validator\IntegerValidator;
use UOPF\Validator\DictionaryValidator;
use UOPF\Exception\CaptchaException;
use UOPF\Exception\ValidationException;
use UOPF\Exception\EmailSendingException;
use UOPF\Exception\DictionaryValidationException;
use UOPF\Interface\Exception\ParameterException;

/**
 * API Endpoint
 */
abstract class Endpoint {
    /**
     * Constructor.
     */
    public function __construct(
        /**
         * The incoming request.
         */
        public readonly Request $request,

        /**
         * The parameters from HTTP query.
         */
        public readonly array $query = []
    ) {}

    /**
     * Handles the incoming request and returns the content of the HTTP response.
     */
    public function generateContent(Response $response): mixed {
        $methods = $this->getMethods();
        $method = $this->request->getMethod();

        if (!isset($methods[$method]))
            $this->throwMethodNotSupportedException($response);

        $callback = [$this, $methods[$method]];
        $content = $callback($response);

        $preprocessor = new Preprocessor($this);
        return $preprocessor->preprocess($content);
    }

    /**
     * Handles an HTTP GET request.
     */
    public function read(Response $response): mixed {
        $this->throwMethodNotSupportedException($response);
    }

    /**
     * Handles an HTTP HEAD request.
     */
    public function headers(Response $response): void {
        $this->read($response); // Discard return value.
    }

    /**
     * Handles an HTTP POST request.
     */
    public function write(Response $response): mixed {
        $this->throwMethodNotSupportedException($response);
    }

    /**
     * Handles an HTTP DELETE request.
     */
    public function delete(Response $response): mixed {
        $this->throwMethodNotSupportedException($response);
    }

    /**
     * Handles an HTTP OPTIONS request.
     */
    public function options(Response $response): mixed {
        return [];
    }

    /**
     * Checks whether it is in the administrative context.
     */
    public function isAdministrativeContext(): bool {
        $context = $this->request->headers->get('X-API-Context');
        return isset($context) && strtolower($context) === 'administration';
    }

    /**
     * Checks whether it is in administrative mode.
     */
    public function isAdministrative(): bool {
        if (!$this->isAdministrativeContext())
            return false;

        if (!isset($this->request->user))
            return false;

        if (!$this->request->user->isAdministrator())
            return false;

        return true;
    }

    /**
     * Checks whether the current context has permission to edit an entry.
     */
    public function canEdit(Model $entry): bool {
        if ($this->isAdministrative())
            return true;

        if (!isset($this->request->user))
            return false;

        if ($entry->canBeEditedBy($this->request->user))
            return true;

        return false;
    }

    /**
     * Returns the HTTP query of the incoming request after validation and filtering.
     */
    protected function filterQuery(DictionaryValidator $validator): mixed {
        return static::filterInput($this->request->query->all(), $validator);
    }

    /**
     * Returns the HTTP body of the incoming request after validation and filtering.
     */
    protected function filterBody(DictionaryValidator $validator): mixed {
        return static::filterInput($this->request->getPayload()->all(), $validator);
    }

    /**
     * Validates the captcha and throws an exception if it fails.
     */
    protected function validateCaptcha(string $captcha, ?string $field = 'captcha'): void {
        $address = $this->request->getClientIp();

        if (!isset($address))
            throw new ParameterException('Unable to read the client IP address.');

        try {
            Captcha::validate($captcha, $address);
        } catch (CaptchaException $exception) {
            if (Services::isDevelopment())
                $data = ['response' => $exception->response];
            else
                $data = [];

            $message = $exception->getMessage();
            throw new ParameterException($message, $field, $data, $exception);
        }
    }

    /**
     * Returns the user ID after validation and filtering.
     */
    protected function filterUserParameterInQuery(mixed $value): int {
        if ($value === 'current') {
            if (isset($this->request->user))
                return $this->request->user['id'];
            else
                $this->throwUnauthorizedException();
        } else {
            return $this->filterIdParameterInQuery($value);
        }
    }

    /**
     * Returns the ID after validation and filtering.
     */
    protected function filterIdParameterInQuery(mixed $value): int {
        try {
            return (new IntegerValidator(min: 1))->filter($value);
        } catch (ValidationException) {
            $this->throwNotFoundException();
        }
    }

    /**
     * Throws a "Permission denied" exception.
     */
    protected function throwPermissionDeniedException(): never {
        if (isset($this->request->user))
            throw new Exception('Permission denied.', 403);
        else
            $this->throwUnauthorizedException();
    }

    /**
     * Throws a "Not found" exception.
     */
    protected function throwNotFoundException(): never {
        throw new Exception('Not found.', 404);
    }

    /**
     * Throws a "Method not allowed" exception.
     */
    protected function throwMethodNotSupportedException(Response $response): never {
        $methods = $this->getAllowedMethods();

        if (in_array('GET', $methods, true) && !in_array('HEAD', $methods))
            $methods[] = 'HEAD';

        if (!in_array('OPTIONS', $methods, true))
            $methods[] = 'OPTIONS';

        $response->headers->set('Allow', implode(', ', $methods));
        throw new Exception('Method not allowed.', 405);
    }

    /**
     * Throws an "Unauthorized" exception.
     */
    protected function throwUnauthorizedException(): never {
        throw new Exception('Unauthorized.', 401);
    }

    /**
     * Throws an "Inconsistent internal data" exception.
     */
    protected function throwInconsistentInternalDataException(): never {
        throw new UOPFException('Inconsistent internal data.');
    }

    /**
     * Returns the allowed HTTP methods for the endpoint.
     */
    protected function getAllowedMethods(): array {
        $class = get_class($this);
        $reflection = new ReflectionClass($class);
        $allowed = [];

        $methods = static::getMethods();
        $methods = array_flip($methods);

        foreach ($reflection->getMethods() as $method) {
            if ($method->class !== $class)
                continue;

            if (isset($methods[$method->name]))
                $allowed[] = $methods[$method->name];
        }

        return $allowed;
    }

    protected static function setTokenOnResponse(Response $response, UserModel $user, int $expirationTime): void {
        $token = $user->calculateToken($expirationTime);
        $response->headers->set('X-API-Token', $token);
    }

    protected static function setPagingOnResponse(Response $response, int $total, int $perPage): void {
        $response->headers->set('X-API-Total', strval($total));
        $response->headers->set('X-API-TotalPages', strval(ceil($total / $perPage)));
    }

    /**
     * Callback for the router.
     */
    public static function serve(Request $request, Response $response, array $query = []): mixed {
        $instance = new static($request, $query);
        return $instance->generateContent($response);
    }

    /**
     * Returns the supported HTTP methods and the names of their corresponding handlers.
     */
    public static function getMethods(): array {
        return [
            'GET' => 'read',
            'HEAD' => 'headers',
            'POST' => 'write',
            'DELETE' => 'delete',
            'OPTIONS' => 'options'
        ];
    }

    /**
     * Returns user input after validation and filtering.
     */
    protected static function filterInput(mixed $input, DictionaryValidator $validator): mixed {
        try {
            return $validator->filter($input);
        } catch (DictionaryValidationException $exception) {
            throw new ParameterException(
                $exception->getLabeledMessage(),
                $exception->elementKey
            );
        }
    }

    /**
     * Uses a case to send the validation code and throws an HTTP 500 error if it fails.
     */
    protected static function sendValidationCode(CaseModel $case): void {
        try {
            $case->sendValidationCode();
        } catch (EmailSendingException $exception) {
            throw new Exception('Failed to send email.', 500, previous: $exception);
        }
    }
}
