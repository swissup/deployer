<?php

namespace Deployer;

set('composer_params', ' --verbose --optimize-autoloader --no-progress --no-interaction');
set('php_cli_params', '-d memory_limit=-1');

set('bin/composer', function () {
    if (commandExist('composer')) {
        $composer = locateBinaryPath('composer');
    }
    if (empty($composer)) {
        run("cd {{deploy_path}} && if [ -f composer.phar ]; then curl -sS https://getcomposer.org/installer | {{bin/php}}; fi");
        $composer = '{{deploy_path}}/composer.phar';
    }
    return '{{bin/php}} {{php_cli_params}} ' . $composer;
});

desc('Remove composer[json|lock] and vendor dir in current');
task('composer:current:clear', function () {
    run(
        "cd {{deploy_path}}/current "
        . " && rm -f composer.json composer.lock"
        . " && rm -rf vendor"
    );
})->setPrivate();

desc('The self-update command checks getcomposer.org for newer versions of composer and if found, installs the latest.');
task('composer:selfupdate', function () {
    run("{{bin/sudo}} {{bin/composer}} self-update");
})->setPrivate();
