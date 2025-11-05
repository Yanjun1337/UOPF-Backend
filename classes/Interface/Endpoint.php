<?php
declare(strict_types=1);
namespace UOPF\Interface;

use ReflectionClass;
use UOPF\Captcha;
use UOPF\Request;
use UOPF\Response;
use UOPF\Services;
use UOPF\Model\User as UserModel;
use UOPF\Validator\DictionaryValidator;
use UOPF\Exception\CaptchaException;
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
        public readonly Request $request
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
        return $callback($response);
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
     * Returns the HTTP body of the incoming request after validation and filtering.
     */
    protected function filterBody(DictionaryValidator $validator): mixed {
        try {
            return $validator->filter($this->request->getPayload()->all());
        } catch (DictionaryValidationException $exception) {
            throw new ParameterException(
                $exception->getLabeledMessage(),
                $exception->elementKey
            );
        }
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
     * Throws a 'Method not supported' exception.
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

    /**
     * Callback for the router.
     */
    public static function serve(Request $request, Response $response): mixed {
        $instance = new static($request);
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
}
