<?php
declare(strict_types=1);
namespace UOPF;

use splitbrain\phpcli\CLI;
use splitbrain\phpcli\Options;

final class CommandLineInterface extends CLI {
    protected function setup(Options $options) {
        $options->registerCommand('init', 'Initialize UOPF.');
        $options->registerOption('email', 'Email address for the default administrator account.', 'e', true, 'init');
        $options->registerOption('username', 'Username for the default administrator account.', 'u', true, 'init');
    }

    protected function main(Options $options) {
        if ($options->getCmd() === 'init')
            Initializer::initialize();
        else
            echo $options->help();
    }
}
