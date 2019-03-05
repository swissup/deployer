<?php
namespace Deployer;

require_once CUSTOM_RECIPE_DIR . '/magento2/release.php';
require_once CUSTOM_RECIPE_DIR . '/magento2/deploy.php';
require_once CUSTOM_RECIPE_DIR . '/magento2/success.php';

// see hosts.yml.example .magento2-settings
// Configuration
// set('shared_files', [
//     'app/etc/config.php',
//     'app/etc/env.php',
//     'var/.maintenance.ip',
// ]);
// set('shared_dirs', [
//     'var/log',
//     'var/backups',
//     'pub/media',
// ]);
// set('writable_dirs', [
//     'var',
//     'pub/static',
//     'pub/media',
// ]);
// set('clear_paths', [
//     'var/page_cache/*',
//     'var/cache/*',
//     //'var/composer_home/*',
//     'var/generation/*',
//     // 'var/di/*',
//     // 'var/view_preprocessed/*',
//     // 'var/generation/*',
//     // 'generated/code/*',
//     // 'generated/metadata/*'
//     // 'pub/static/*'
// ]);
set('copy_dirs', function () {
    $vendors = [];

    if (has('previous_release')) {
        $paths = [
            'app/code',
            'app/design/frontend',
            'app/design/adminhtml',
            'app/i18n'
        ];
        foreach ($paths as $path) {
            $_vendors = explode("\n", run("ls {{previous_release}}/{$path}"));
            $_vendors = array_filter($_vendors);
            $_vendors = array_filter($_vendors, function ($vendor) {
                return 'Magento' !== $vendor;
            });
            foreach ($_vendors as $vendor) {
                $vendors[] =  $path .'/' . $vendor;
            }
        }
    }

    return $vendors;
});

task('magento2:init:failed', function () {
    if (test("[ -h {{deploy_path}}/release ]")) {
        $releasePath = get('release_path');
        $release = basename($releasePath);
        $databaseName = get('database_name');

        if (test("[ -d {{deploy_path}}/releases/$release ]")) {
            run("{{bin/sudo}} rm -rf {{deploy_path}}/releases/$release");
        }
        // run("cd {{deploy_path}} && if [ -e release ]; then rm release; fi");
        run("cd {{deploy_path}} && if [ -h release ]; then rm release; fi");

        run("cd {{deploy_path}} && {{bin/mysql}} -Bse 'DROP DATABASE IF EXISTS $databaseName;'");
    }
})->setPrivate();

/**
 * Main task
 * dep magento2:init --packages=swissup/ajaxpro,swissup/ajaxlayerednavigation,swissup/firecheckout,swissup/askit,swissup/testimonials,swissup/sold-together,swissup/rich-snippets,swissup/reviewreminder,swissup/pro-labels,swissup/highlight,swissup/fblike,swissup/easytabs,swissup/easy-slide,swissup/easyflags,swissup/easycatalogimg,swissup/easybanner,swissup/attributepages,swissup/ajaxsearch,swissup/address-field-manager,swissup/argento-m2 -vv
 */
desc('Init new magento2 demo. Options --packages=[], --tag=[]');
task('magento2:init', [
    'deploy:info',
    'deploy:prepare',
    'magento2:deploy:apache:prepare',
    'deploy:lock',
    'magento2:deploy:check',
    'magento2:deploy:release',////////////////
    'magento2:deploy:update_code',
    'magento2:deploy:vendors:preinstall',
    'magento2:deploy:vendors:install',
    'magento2:deploy:create_db',
    'magento2:setup:install',
    'magento2:setup:upgrade',
    'magento2:deploy:sampledata:install',
    'magento2:deploy:vendors:update',
    'magento2:setup:upgrade',
    'magento2:deploy:post:install', //<--
    'magento2:deploy:usermod',
    'magento2:deploy:permissions',
    // 'magento2:app:config:dump',////////////////
    'deploy:shared',
    'deploy:writable',
    'deploy:symlink',
    'deploy:unlock',
    'magento2:success'
]);

fail('magento2:init', 'magento2:init:failed');

desc('Deploy magento2');
task('magento2:deploy', [
    'deploy:info',
    'deploy:prepare',
    'magento2:deploy:apache:prepare',
    'deploy:lock',
    'magento2:deploy:check',
    'magento2:deploy:release',////////////////
    'magento2:deploy:update_code',
    'deploy:shared',// <--
    'magento2:deploy:vendors:preinstall',
    'magento2:deploy:vendors:install',
    'deploy:copy_dirs',
    'magento2:maintenance:enable',
    // 'magento2:app:config:import',
    'magento2:setup:upgrade',
    'magento2:deploy:sampledata:install',
    'magento2:deploy:vendors:update',
    'magento2:deploy:modules:enable',
    'magento2:setup:upgrade',
    'magento2:deploy:post:install', // <--
    'magento2:deploy:usermod',
    'magento2:deploy:permissions',
    // 'magento2:app:config:dump',////////////////
    'magento2:maintenance:disable',
    // 'deploy:shared',
    'deploy:writable',
    'deploy:clear_paths',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
    'magento2:success'
]);
fail('magento2:deploy', 'magento2:init:failed');
