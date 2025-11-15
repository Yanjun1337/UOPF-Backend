<?php
declare(strict_types=1);
namespace UOPF;

use UOPF\Exception\CaptchaException;
use UOPF\Exception\EnvironmentVariableException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use const JSON_ERROR_NONE;

/**
 * Captcha
 */
abstract class Captcha {
    /**
     * Validates a captcha.
     *
     * @see https://docs.hcaptcha.com/
     */
    public static function validate(string $captcha, string $address): void {
        $secret = static::getSecret();
        $sitekey = static::getSitekey();

        $client = new Client([
            'base_uri' => 'https://api.hcaptcha.com',
            'timeout'  => 10.0
        ]);

        try {
            $response = $client->request('POST', '/siteverify', [
                'form_params' => [
                    'secret' => $secret,
                    'response' => $captcha,
                    'remoteip' => $address,
                    'sitekey' => $sitekey
                ]
            ]);
        } catch (GuzzleException $exception) {
            throw new Exception('Failed to connect to hCaptcha server.', previous: $exception);
        }

        $body = strval($response->getBody());
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data))
            throw new Exception('Invalid response from hCaptcha.');

        if (empty($data['success']))
            throw new CaptchaException($data, 'Invalid captcha.');
    }

    /**
     * Returns the secret of hCaptcha.
     */
    protected static function getSecret(): string {
        if (isset($_ENV['UOPF_HCAPTCHA_SECRET']))
            return $_ENV['UOPF_HCAPTCHA_SECRET'];
        else
            throw new EnvironmentVariableException('Secret of hCaptcha is required.', 'UOPF_HCAPTCHA_SECRET');
    }

    /**
     * Returns the sitekey of hCaptcha.
     */
    public static function getSitekey(): string {
        if (isset($_ENV['UOPF_HCAPTCHA_SITEKEY']))
            return $_ENV['UOPF_HCAPTCHA_SITEKEY'];
        else
            throw new EnvironmentVariableException('Sitekey of hCaptcha is required.', 'UOPF_HCAPTCHA_SITEKEY');
    }
}
