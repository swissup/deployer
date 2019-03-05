<?php
namespace Deployer;

require 'recipe/magento.php';

require_once CUSTOM_RECIPE_DIR . '/magento1/deploy.php';
require_once CUSTOM_RECIPE_DIR . '/magento1/release.php';

// see hosts.yml.example .magento1-settings
// set('repository', 'git@github.com:OpenMage/magento-mirror.git');
// set('repository', 'git@github.com:colinmollenhour/magento-lite.git');
// set('repository', 'git@github.com:speedupmate/Magento-CE-Mirror.git');
// set('shared_dirs', ['var', 'media']);
// set('shared_files', ['app/etc/local.xml']);
// set('writable_dirs', ['var', 'media']);


// task('magento:shared', function () {
//     $sharedPath = "{{deploy_path}}/shared";
//     $samplePath = get('magento_sample_data');
//     $sampleDataCopyDirs = get('sample_data_copy_dirs');
//     foreach (get('shared_dirs') as $dir) {
//         //copy sample data [media]
//         if (in_array($dir, $sampleDataCopyDirs)) {
//             // write("cp -rpf {{deploy_path}}/$samplePath/$dir $sharedPath");
//             run("cp -rpf {{deploy_path}}/$samplePath/$dir $sharedPath");
//             // // Create path to shared dir in release dir if it does not exist
//             // // (symlink will not create the path and will fail otherwise)
//             // run("mkdir -p `dirname {{release_path}}/$dir`");
//             // // Symlink shared dir to release dir
//             // run("{{bin/symlink}} $sharedPath/$dir {{release_path}}/$dir");
//         }

//     }
// })->desc('Copy shared sample data');

// option(
//     'argento-theme',
//     null,
//     \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL,
//     'Argento theme. (default pure2)'
// );
// set('argento_theme', function () {
//     $theme = 'Pure2';
//     if (input()->hasOption('argento-theme')) {
//         $theme = input()->getOption('argento-theme');
//     }
//     if (empty($theme)) {
//         $theme = 'Pure2';
//     }
//     return ucfirst($theme);
// });

// task('magento:argento:activate', function () {

//     if (input()->hasOption('packages')) {
//         $packages = input()->getOption('packages');
//         if (false === strpos($packages, 'tm/argento')
//             && false === strpos($packages, 'tm/*')) {

//             return;
//         }
//     }

//     $theme = get('argento_theme');

//     $code = "require_once 'app/Mage.php'; umask(0);"
//         . "Mage::app('admin', 'store');"
//         . "Mage::getModel('tmcore/module')->load('TM_Argento" . $theme . "')"
//         . "->setNewStores(array(0,1,2,3))->setIdentityKey('hello')->up();";

//     // writeln("cd {{release_path}} && php -r \"$code\"");
//     run("cd {{release_path}} && php -r \"$code\"");
// })->desc('Activate Argento');

task('magento:init:failed', function () {
    $releasePath = get('release_path');
    $release = basename($releasePath);
    $databaseName = get('database_name');

    // run("cd {{deploy_path}} && if [ -e release ]; then rm release; fi");
    run("cd {{deploy_path}} && if [ -h release ]; then rm release; fi");
    run("{{bin/sudo}} rm -rf {{deploy_path}}/releases/$release");
    run("{{bin/mysql}} -Bse 'DROP DATABASE IF EXISTS $databaseName;'");
})->setPrivate();

/**
 * Main task
 * dep6 magento:init --packages=tm/ajax-pro:\*,tm/ajax-layered-navigation:\*,tm/ajax-search:\*,tm/ask-it:\*,tm/easy-banner:\*,tm/helpdesk:\*,tm/navigation-pro:\*,tm/cache:\*,tm/highlight:\*,tm/pro-labels:\*,tm/review-reminder:\*,tm/sold-together:\*
 */
desc('Create magento demo');
desc('Create new magento demo. Options --packages=[], --tag=[]');
task('magento:init', [
    'deploy:info',
    'magento:release:check',
    'deploy:prepare',
    'deploy:lock',
    'magento:deploy:release',
    'magento:deploy:update_code',
    'magento:deploy:apache:htaccess',
    'magento:release:sampledata:dir',
    'magento:release:sampledata:db',
    'magento:release:setup:install',
    'magento:release:packages:install',
    'magento:release:permission',
    'magento:release:post:install',
    'deploy:shared',
    'deploy:writable',
    'deploy:unlock',
    'magento:release:success',
    'deploy:symlink'
]);

fail('magento:init', 'magento:init:failed');

/**
 * Clear cache
 */
task('deploy:cache:clear', function () {
    run("cd {{release_path}} && php -r \"require_once 'app/Mage.php'; umask(0); Mage::app()->cleanCache();\"");
})->desc('Clear cache');

desc('Deploy your magento project');
task('magento:deploy', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'magento:deploy:release',
    'magento:deploy:update_code',
    'magento:release:packages:install',
    'deploy:shared',
    'deploy:writable',
    'deploy:cache:clear',
    'magento:release:success',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup'
]);
fail('magento:deploy', 'magento:init:failed');
