<?php

namespace Deployer;

require 'recipe/common.php';

require_once CUSTOM_RECIPE_DIR . '/common.php';
// require_once CUSTOM_RECIPE_DIR . '/common.php';y
require_once CUSTOM_RECIPE_DIR . '/releases.php';
require_once CUSTOM_RECIPE_DIR . '/bin/composer.php';
require_once CUSTOM_RECIPE_DIR . '/bin/magento.php';
require_once CUSTOM_RECIPE_DIR . '/bin/magerun2.php';
require_once CUSTOM_RECIPE_DIR . '/bin/mysql.php';
require_once CUSTOM_RECIPE_DIR . '/bin/jq.php';
require_once CUSTOM_RECIPE_DIR . '/bin/sudo.php';
require_once CUSTOM_RECIPE_DIR . '/options/packages.php';
require_once CUSTOM_RECIPE_DIR . '/options/modules.php';

require_once CUSTOM_RECIPE_DIR . '/magento2/deploy/check.php';
require_once CUSTOM_RECIPE_DIR . '/magento2/deploy/release.php';
require_once CUSTOM_RECIPE_DIR . '/magento2/deploy/update_code.php';
require_once CUSTOM_RECIPE_DIR . '/magento2/deploy/vendors.php';
require_once CUSTOM_RECIPE_DIR . '/magento2/deploy/create_db.php';
require_once CUSTOM_RECIPE_DIR . '/magento2/deploy/sampledata.php';
require_once CUSTOM_RECIPE_DIR . '/magento2/deploy/permissions.php';

desc('Install magerun 2 (bin/magento setup:install)');
task('magento2:setup:install', function () {
    // $releasePath = get('release_path');
    // $release = basename($releasePath);
    $databaseName = get('database_name');
    $adminPassword = get('admin_password');

    $_options = [
        'admin-firstname'   => 'John',
        'admin-lastname'    => 'Doe',
        'admin-email'       => 'john.doe@gmail.com',
        'admin-user'        => 'admin',
        'admin-password'    => $adminPassword,
        'base-url'          => get('base_url'),
        'backend-frontname' => 'admin',
        'db-host'           => get('mysql_host'),
        'db-user'           => get('mysql_user'),
        'db-password'       => get('mysql_pass'),
        'db-name'           => $databaseName,
        'language'          => 'en_US',
        'currency'          => 'USD',
        'timezone'          => 'America/Chicago',
        'use-rewrites'      => '1',
        'cleanup-database'  => '',
        // 'use-sample-data'   => ''
    ];

    $options = "";
    foreach ($_options as $key => $value) {
        $options .= ' --' . ('' === $value ? "$key" :"$key=\"$value\"");
    }

    // writeln("{{bin/magento}} setup:install $options");
    run("cd {{release_path}} && {{bin/magento}} setup:install $options", [
        'timeout' => 600
    ]);
})->setPrivate();

// before('magento2:deploy:vendors:install', 'magento2:deploy:vendors:preinstall');
// before('magento2:deploy:sampledata:install', 'magento2:deploy:vendors:preinstall');
//
task('magento2:setup:upgrade', function () {
    run("cd {{release_path}} && {{bin/magento}} setup:upgrade", [
        'timeout' => 600
    ]);
})->setPrivate();

desc('Enable magento 2 maintenance');
task('magento2:maintenance:enable', function () {
    run("cd {{release_path}} && {{bin/magento}} maintenance:enable");
})->setPrivate();

desc('Disable magento 2 maintenance');
task('magento2:maintenance:disable', function () {
    run("cd {{release_path}} && {{bin/magento}} maintenance:disable");
})->setPrivate();

desc('magento 2 shared dump config');
task('magento2:app:config:dump', function () {
    cd('{{release_path}}');
    // if (commandSupportsOption('{{bin/magento}}', 'app:config:dump')) {
        run("cd {{release_path}} && {{bin/magento}} app:config:dump");
    // }
})->setPrivate();

desc('magento 2 import config');
task('magento2:app:config:import', function () {
    cd('{{release_path}}');
    // if (commandSupportsOption('{{bin/magento}}', 'app:config:import')) {
        run("cd {{release_path}} && {{bin/magento}} app:config:import");
    // }
})->setPrivate();

task('magento2:mode:developer', function () {
    run("cd {{release_path}} && {{bin/magento}} deploy:mode:set developer");
})->setPrivate();

task('magento2:setup:di:compile', function () {
    run("cd {{release_path}} && {{bin/magento}} setup:di:compile", [
        'timeout' => 600
    ]);
    run("cd {{release_path}} && {{bin/composer}} dump-autoload -o");
})->setPrivate();

task('magento2:setup:static-content:deploy', function () {
    $locale = 'en_US';
    run("cd {{release_path}} && {{bin/magento}} setup:static-content:deploy -f {$locale}", [
        'timeout' => 1200
    ]);
})->setPrivate();

task('magento2:indexer:reindex', function () {
    run("cd {{release_path}} && {{bin/magento}} indexer:set-mode schedule");
    run("cd {{release_path}} && {{bin/magento}} indexer:reindex");
})->setPrivate();

task('magento2:cache:clean', function () {
    run("cd {{release_path}} && {{bin/magento}} cache:clean");
})->setPrivate();

task('magento2:cache:flush', function () {
    run("cd {{release_path}} && {{bin/magento}} cache:flush");
})->setPrivate();

task('magento2:cron:run', function () {
    run("cd {{release_path}} && {{bin/magento}} cron:run");
})->setPrivate();

task('magento2:disable_static_sign', function () {
    run("cd {{release_path}} && {{bin/magento}} config:set dev/static/sign 0 --lock-env");
    run("cd {{release_path}} && {{bin/magento}} cache:clean config");
})->setPrivate();

desc('Magento 2 after installation configuration (cache clean, set pass)');
task('magento2:deploy:post:install', [
    'magento2:mode:developer',
    'magento2:setup:di:compile',
    'magento2:setup:static-content:deploy',
    'magento2:indexer:reindex',
    'magento2:cache:flush',
    'magento2:cron:run',
    'magento2:disable_static_sign'
]);
