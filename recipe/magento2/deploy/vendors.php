<?php

namespace Deployer;

require_once CUSTOM_RECIPE_DIR . '/bin/composer.php';

desc('Create auth.json if not exist and add repo.magento.com credentials. Set composer minimum-stability="dev"');
task('magento2:deploy:vendors:preinstall', function () {
    // $username = get('magento_composer_username');
    // $password = get('magento_composer_password');
    try {
        //>whereis composer
        //>sudo ln -s /usr/bin/composer /usr/local/bin/composer
        $username = runLocally('{{bin/composer}} global config http-basic.repo.magento.com.username');
        $password = runLocally('{{bin/composer}} global config http-basic.repo.magento.com.password');
    } catch (RuntimeException $e) {
        $message = 'Get and set your magento <a href="https://devdocs.magento.com/guides/v2.3/install-gde/prereq/connect-auth.html">Access Keys</a>';
        $message .= "\n{bin/composer}} config -g http-basic.repo.magento.com [Public Key] [Private Key]";
        $message .= "\n" . $e->getMessage();

        throw new Exception($message);
    }
    run("cd {{release_path}} && {{bin/composer}} config http-basic.repo.magento.com $username $password");
    run("cd {{release_path}} && {{bin/composer}} config repositories.0 composer https://repo.magento.com");

    run("cd {{release_path}} && {{bin/composer}} config minimum-stability dev");
    // run("cd {{release_path}} && {{bin/composer}} config secure-http false");
    // run("cd {{release_path}} && {{bin/composer}} discard-changes true");

    run("cd {{release_path}} && {{bin/composer}} config repositories.swissup composer https://docs.swissuplabs.com/packages/");
})->setPrivate();

////////////////////////////////////////////////////////////////////////////////
desc('Run composer install command in current mage 2 instance');
task('magento2:deploy:vendors:install', function () {
    // fix for https://github.com/magento/magento2/issues/24937
    // run("cd {{release_path}} && {{bin/composer}} require -n --no-update --ignore-platform-reqs symfony/service-contracts:v1.1.6 ");
    // run("cd {{release_path}} && {{bin/composer}} update symfony/service-contracts --ignore-platform-reqs");

    run("cd {{release_path}} && {{bin/composer}} install {{composer_params}}");
})->setPrivate();

////////////////////////////////////////////////////////////////////////////////
desc('Install swissup packages (composer require)');
task('magento2:deploy:vendors:update', function () {
    $packages = get('option_packages');
    if (empty($packages)) {
        return;
    }
    foreach ($packages as $package) {
        if (empty($package)) {
            continue;
        }
        // list($_package, $version) = explode(':', $package, 2);
        run(
            "cd {{release_path}}"
            // . " && {{bin/composer}} config repositories.$_package vcs git@github.com:$_package.git"
            . " && {{bin/composer}} require -n --no-update --ignore-platform-reqs $package"
        );
    }

    $packages = implode(' ', $packages);
    $packages = str_replace(':*', '', $packages);

    run("cd {{release_path}} && {{bin/composer}} update $packages --ignore-platform-reqs", [
        'timeout' => 600
    ]);
    // run("cd {{release_path}} && {{bin/composer}} update {{composer_params}}");
    // run("cd {{release_path}} && {{bin/magento}} setup:upgrade", [
    //     'timeout' => 600
    // ]);
})->setPrivate();
