<?php
namespace Deployer;

require_once __DIR__ . '/../magento1.php';

set('use_relative_symlink', false);
set('shared_dirs', []);
set('shared_files', []);

set('skeleton_path', function () {
    return str_replace("\n", '', run("cd {{deploy_path}} && if [ -d skeleton ]; then readlink skeleton; fi"));
    // return str_replace("\n", '', run("readlink {{deploy_path}}/skeleton"));
});

desc('Show path to current skeleton directory');
task('magento:skeleton:path', function () {
    writeln(get('skeleton_path'));
})->setPrivate();

set('magento_skeleton_list', function () {
    $releases = get('magento_releases_list');
    $list = [];
    $fingerprint = 'No modules match the specified criteria';
    $modules = get('option_modules');
    // print_r($modules);
    // $marker = md5(time());
    foreach ($releases as $release) {
        $releaseDir = "{{deploy_path}}/releases/{$release}";

        $file = "$releaseDir/maintenance.flag";
        // writeln($status);
        if (!test("[ -f $file ]")) {
            continue;
        }
        // $magerunList = run("cd {$releaseDir} && {{bin/magerun}} sys:modules:list --vendor=TM");
        // writeln($magerunList);
        // if (false !== strpos($magerunList, $fingerprint)) {
        //     continue;
        // }
        $add = true;
        foreach ($modules as $module) {
            // if (false === strpos($magerunList, $module)) {
            //     $add = false;
            //     continue;
            // }

            $file = "$releaseDir/app/etc/modules/$module.xml.off";
            // $status = run("if [ -f $file ]; then echo '$marker'; fi");
            // $status = test("[ -f $file ]");
            // writeln(" $module : " . ($status !== $marker ? 'false' : 'true'));
            if (test("[ ! -f $file ]")) {
                $add = false;
            }
        }

        if ($add) {
            $list[] = $release;
        }
    }
    // print_r($list);
    return $list;
});

task('magento:skeleton:list', function () {
    $releases = get('magento_skeleton_list');
    if (!empty($releases)) {
        $releases = implode("\n", $releases);
        writeln("$releases");
    }
})->desc('List skeleton ready to summon');

set('releases_skeleton_store', 1);

task('magento:skeleton:check', function () {
    $marker = uniqid();
    $status = run("if [ -d {{deploy_path}}/skeleton ]; then echo '$marker'; fi");
    // writeln("$marker $status" . ($status === $marker ? 'true' : 'false') );
    if ($status === $marker) {
        $skeleton = run("readlink {{deploy_path}}/skeleton");
        $skeleton = explode('/', $skeleton);
        $skeleton = end($skeleton);
        $skeleton = substr($skeleton, 4);
        $skeleton = strtotime($skeleton);

        if (14400 > time() - $skeleton) {
            writeln("<error>Resource 'skeleton' is busy</error>");
            die;
        } else {
            writeln("<error>Resource 'skeleton' is old and has been removed</error>");
            run("cd {{deploy_path}} && rm -rf skeleton ");
        }
    }
})->setPrivate();

desc('Set current skeleton');
task('magento:skeleton:set', function () {
    $list = get('magento_skeleton_list');
    if (empty($list)) {
        writeln("<error>Looks like we have no free magento release.</error>");
        die;
        throw new RuntimeException(
            "Looks like we have no free magento release.\n"
        );
    }
    $release = end($list);
    $releaseDir = get('deploy_path') . "/releases/$release";
    $marker = uniqid();
    $status = run("if [ -d {{deploy_path}}/skeleton ]; then echo '$marker'; fi");
    if ($status === $marker) {
        writeln("<error>Resource 'skeleton' is busy</error>");
        die;
    }
    run("cd {{deploy_path}} && {{bin/symlink}} $releaseDir skeleton");
})->setPrivate();

task('magento:skeleton:maintenance:disable', function () {
    run("cd {{skeleton_path}} && {{bin/magerun}} sys:maintenance --off");
})->setPrivate()
->desc('Disable maintenance mode');

task('magento:skeleton:modules:enable', function () {

    $modules = get('option_modules');
    $root = get('skeleton_path');

    foreach ($modules as $module) {
        $moduleName = $module;
        $fileOff = "$root/app/etc/modules/$moduleName.xml.off";
        $file = "$root/app/etc/modules/$moduleName.xml";
        run("cd {{skeleton_path}} && if [ -f $fileOff ]; then mv --force $fileOff $file; fi");
    }

    // run("cd {{skeleton_path}} && {{bin/magerun}} sys:setup:run");//
})->setPrivate()->desc('Enabling all modules');

task('magento:skeleton:modules:up', function () {

    $modules = get('option_modules');
    foreach ($modules as $module) {
        $code = "require_once 'app/Mage.php'; umask(0);"
            . "Mage::app('admin', 'store');"
            . "Mage::getModel('tmcore/module')->load('" . $module . "')"
            . "->setNewStores(array(0,1,2,3))->setIdentityKey('hello')->up();";

        // writeln("cd {{skeleton_path}} && php -r \"$code\"");
        run("cd {{skeleton_path}} && php -r \"$code\"");
    }
})->setPrivate();

desc('Show after full installation success info');
task('magento:skeleton:success', function () {
    $baseUrl = get('base_url');
    $releasePath = get('skeleton_path');
    $release = basename($releasePath);

    writeln("Dir : <comment>$releasePath</comment>");
    writeln("Db  : <comment>db$release</comment>");
    writeln("Url : <comment>$baseUrl/releases/$release</comment>");
    writeln("Admin Url : <comment>$baseUrl/releases/$release/index.php/admin admin db$release</comment>");
})->setPrivate();

desc('Show after full installation success info');
task('magento:skeleton:rm', function () {
    $releasePath = get('deploy_path') . '/skeleton';
    run("if [ -h $releasePath ]; then rm $releasePath; fi");
})->setPrivate();

//dep magento:skeleton:summon --modules=TM_CatalogConfigurableSwatches,TM_Core,TM_AjaxPro,TM_AjaxLayeredNavigation,TM_AjaxSearch,TM_Akismet,TM_Email,TM_AskIt,TM_EasyBanner,TM_Purify,TM_Helpmate,TM_NavigationPro,TM_Cache,TM_Highlight,TM_ProLabels,TM_ReviewReminder,TM_SuggestPage,TM_SoldTogether
task('magento:skeleton:summon', [
    'magento:skeleton:check',
    'magento:skeleton:set',
    'magento:skeleton:maintenance:disable',
    'magento:skeleton:modules:enable',
    'magento:skeleton:modules:up',
    'magento:skeleton:success',
    'magento:skeleton:rm'
])->desc("Combine task for enables modules in skeleton");

set('skeleton_store', 5);
//magento:skeleton:create --packages=tm/ajax-pro:\*,tm/ajax-layered-navigation:\*,tm/ajax-search:\*,tm/ask-it:\*,tm/easy-banner:\*,tm/helpdesk:\*,tm/navigation-pro:\*,tm/cache:\*,tm/highlight:\*,tm/pro-labels:\*,tm/review-reminder:\*,tm/sold-together:\*
desc('Check magento store and create new if need');
task('magento:skeleton:create', function () {
    $releases = get('magento_skeleton_list');
    $store = get('skeleton_store');
    if (count($releases) >= $store) {
        die;
    }
});

desc('Create magento demo prepared skeleton for magento:modules');
task('magento:skeleton:prepare', [
    'magento:release:check',
    'deploy:prepare',
    'magento:release:deploy',
    'magento:release:git:clone',
    'deploy:shared',
    'deploy:writable',
    'magento:release:sampledata:dir',
    'magento:release:sampledata:db',
    'magento:release:setup:install',
    'magento:release:packages:install',
    'magento:release:permission',
    'magento:release:post:install',
    // 'magento:argento:activate',
    'magento:release:modules:disable:all',
    'magento:release:maintenance:enable',
    'magento:release:success',
    'magento:release:deploy:symlink'
])->setPrivate();

after('magento:skeleton:create', 'magento:skeleton:prepare');
