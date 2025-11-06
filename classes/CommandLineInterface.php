<?php
declare(strict_types=1);
namespace UOPF;

use UOPF\Validator\StringValidator;
use UOPF\Validator\DictionaryValidator;
use UOPF\Validator\DictionaryValidatorElement;
use UOPF\Validator\Extension\UsernameValidator;
use UOPF\Exception\DictionaryValidationException;
use PragmaRX\Random\Random;
use splitbrain\phpcli\CLI;
use splitbrain\phpcli\Options;

final class CommandLineInterface extends CLI {
    protected function setup(Options $options) {
        $options->registerCommand('init', 'Initialize UOPF.');
        $options->registerOption('email', 'Email address for the default administrator account.', 'e', true, 'init');
        $options->registerOption('username', 'Username for the default administrator account.', 'u', true, 'init');
    }

    protected function main(Options $options) {
        if ($options->getCmd() === 'init') {
            try {
                $filtered = (new DictionaryValidator([
                    'email' => new DictionaryValidatorElement(
                        label: 'Email Address',
                        required: true,

                        validator: new StringValidator(
                            max: 128,
                            format: 'email'
                        )
                    ),

                    'username' => new DictionaryValidatorElement(
                        label: 'Username',
                        required: true,
                        validator: new UsernameValidator()
                    )
                ]))->filter([
                    'email' => $options->getOpt('email'),
                    'username' => $options->getOpt('username')
                ]);
            } catch (DictionaryValidationException $exception) {
                throw new Exception($exception->getLabeledMessage());
            }

            $random = new Random();
            $password = $random->get();

            Initializer::initialize(
                email: $filtered['email'],
                username: $filtered['username'],
                password: $password
            );

            $message = "UOPF is initialized successfully!\n\n";
            $message .= "Password for the default administrator ({$filtered['username']}) is:\n";
            $message .= $password;

            $this->info($message);
        } else {
            echo $options->help();
        }
    }
}
