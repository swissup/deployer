<?php

namespace Deployer;

require_once CUSTOM_RECIPE_DIR . '/common.php';
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
require_once CUSTOM_RECIPE_DIR . '/magento2/deploy/create_db.php';
require_once CUSTOM_RECIPE_DIR . '/magento2/deploy/modules.php';
require_once CUSTOM_RECIPE_DIR . '/magento2/deploy/permissions.php';
require_once CUSTOM_RECIPE_DIR . '/magento2/deploy/prepare.php';
require_once CUSTOM_RECIPE_DIR . '/magento2/deploy/release.php';
require_once CUSTOM_RECIPE_DIR . '/magento2/deploy/sampledata.php';
require_once CUSTOM_RECIPE_DIR . '/magento2/deploy/update_code.php';
require_once CUSTOM_RECIPE_DIR . '/magento2/deploy/vendors.php';

desc('Install magerun 2 (bin/magento setup:install)');
task('magento2:setup:install', function () {
    // $releasePath = get('release_path');
    // $release = basename($releasePath);
    $databaseName = get('database_name');
    $adminPassword = get('admin_password');

    $_options = [
        'admin-firstname'   => get('admin-firstname'),
        'admin-lastname'    => get('admin-lastname'),
        'admin-email'       => get('admin-email'),
        'admin-user'        => get('admin-user'),
        'admin-password'    => $adminPassword,
        'base-url'          => get('base_url'),
        'backend-frontname' => get('backend-frontname'),
        'db-host'           => get('mysql_host'),
        'db-user'           => get('mysql_user'),
        'db-password'       => get('mysql_pass'),
        'db-name'           => $databaseName,
        'language'          => get('language'),
        'currency'          => get('currency'),
        'timezone'          => get('timezone'),
        'use-rewrites'      => get('use-rewrites'),
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
})->setPrivate();

task('magento2:composer:dump-autoload', function () {
    run("cd {{release_path}} && {{bin/composer}} dump-autoload -o");
})->setPrivate();

task('magento2:rm-outdated', function () {
    run("cd {{release_path}} && {{bin/sudo}} rm -rf pub/static/_requirejs var/view_preprocessed pub/static/frontend/ pub/static/adminhtml/ generated/code/");
})->setPrivate();

task('magento2:setup:static-content:deploy', function () {
    // $locale = 'en_US';
    $locale = get('language');
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

// desc('Add clean cache cronjob');
// task('magento2:cache:clean:cronjob', function () {
//     $cronJobKey = hash('crc32', get('hostname')) . '_CRON_JOBS_CACHE_CLEAN';

//     //delete all cronjobs with unique key
//     $resetCronJobsFromDeployment = sprintf('crontab -l | grep -v "%s" | crontab -', $cronJobKey);
//     writeln('Resetting crontab list using key: ' . $cronJobKey . ' (' . get('hostname') . ')');
//     run($resetCronJobsFromDeployment);

//     $cronjob = parse("{{bin/magerun2}} cache:clean --quiet --root-dir={{deploy_path}}/current");
//     $time = '0 */12 * * * ';
//     // $time = '*/10 * * * * ';
//     $cronjob = $time . $cronjob;
//     $cronjob = sprintf('%s #%s', $cronjob, $cronJobKey);
//     writeln('Adding cron');
//     writeln($cronjob);

//     run('(crontab -l ; echo "' . $cronjob . '") | crontab -');
// });

task('magento2:cache:flush', function () {
    run("cd {{release_path}} && {{bin/magento}} cache:flush");
})->setPrivate();

task('magento2:cron:run', function () {
    run("cd {{release_path}} && {{bin/magento}} cron:run");
})
->setPrivate();

task('magento2:disable_static_sign', function () {
    run("cd {{release_path}} && {{bin/magento}} config:set dev/static/sign 0 --lock-env");
    run("cd {{release_path}} && {{bin/magento}} cache:clean config");
})->setPrivate();

task('magento2:security:unforce', function () {
    run("cd {{release_path}} && {{bin/magento}} config:set admin/security/password_is_forced 0 --lock-config");
    run("cd {{release_path}} && {{bin/magento}} cache:clean config");
})->setPrivate();

desc('Magento 2 after installation configuration (cache clean, set pass)');
task('magento2:deploy:post:install', [
    'magento2:mode:developer',
    // 'magento2:rm-outdated',
    'deploy:clear_paths',
    'magento2:setup:static-content:deploy',
    'magento2:setup:di:compile',
    // 'magento2:composer:dump-autoload',
    'magento2:indexer:reindex',
    'magento2:cache:flush',
    // 'magento2:cron:run',
    'magento2:disable_static_sign',
    'magento2:security:unforce'
])->setPrivate();
