<?php

namespace Deployer;

set('composer_params', ' --verbose --optimize-autoloader --no-progress --no-interaction');

set('bin/composer', function () {
    if (commandExist('composer')) {
        $composer = locateBinaryPath('composer');
    }
    if (empty($composer)) {
        run("cd {{deploy_path}} && if [ -f composer.phar ]; then curl -sS https://getcomposer.org/installer | {{bin/php}}; fi");
        $composer = '{{deploy_path}}/composer.phar';
    }
    return '{{bin/php}} ' . $composer;
});

desc('Remove composer[json|lock] and vendor dir in current');
task('composer:current:clear', function () {
    run(
        "cd {{deploy_path}}/current "
        . " && rm -f composer.json composer.lock"
        . " && rm -rf vendor"
    );
})->setPrivate();
