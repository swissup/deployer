<?php
namespace Deployer;

require_once __DIR__ . '/../magento2.php';

// set('shared_dirs', []);
// set('shared_files', []);

set('magento2_skeleton_temp_dir', 'skeletonm2');

set('skeleton_path', function () {
    return str_replace("\n", '', run("cd {{deploy_path}} && if [ -d skeleton ]; then readlink skeleton; fi"));
    // return str_replace("\n", '', run("readlink {{deploy_path}}/skeleton"));
});

set('magento2_skeleton_list', function () {
    $releases = get('magento2_releases_list');
    $list = [];
    $fingerprint1 = 'Status: maintenance mode is active';
    $fingerprint2 = 'Swissup';
    $modules = get('option_modules');
    foreach ($releases as $release) {
        $releaseDir = "{{deploy_path}}/releases/{$release}";

        $status = run("cd {$releaseDir} && {{bin/magento}} maintenance:status");
        // writeln("$release $status " . (false === strpos($status, $fingerprint1) ? 'true' : 'false'));
        if (false === strpos($status, $fingerprint1)) {
            continue;
        }

        $magerunList = run("cd {$releaseDir} && {{bin/magento}} module:status");
        // writeln("$haystack");
        $add = true;
        foreach ($modules as $module) {
            // writeln("$module " . (false === strpos($magerunList, $module) ? 'true' : 'false'));
            if (false === strpos($magerunList, $module)) {
                $add = false;
            }
        }

        if ($add) {
            $list[] = $release;
        }
        // $list[] = $release;
    }
    return $list;
});

task('magento2:skeleton:check', function () {
    if (test("[ -d {{deploy_path}}/skeleton ]")) {
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

desc('Set current magento 2 skeleton ready for summon');
task('magento2:skeleton:set', function () {
    $list = get('magento2_skeleton_list');
    if (empty($list)) {
        throw new RuntimeException(
            "Looks like we have no free magento release.\n"
        );
    }
    $release = end($list);
    // $release = current($list);

    // Symlink to old release.
    $releaseDir = get('deploy_path') . "/releases/$release";
    if (test("[ -d {{deploy_path}}/skeleton ]")) {
        writeln("<error>Resource 'skeleton' is busy</error>");
        die;
    }
    run("cd {{deploy_path}} && {{bin/symlink}} $releaseDir skeleton");
})->setPrivate();

desc('Disable magento 2 maintenance');
task('magento2:skeleton:maintenance:disable', function () {
    run("cd {{skeleton_path}} && {{bin/magento}} maintenance:disable");
})->setPrivate();

// after packages:install
desc('Disabling all swissup magento 2 modules');
task('magento2:release:modules:disabled:all', function () {
    $status = run("cd {{release_path}} && {{bin/magento}} module:status");
    $rm = 'None';
    $status = str_replace($rm, '', $status);
    $delimiter ='List of disabled modules:';
    list($enabled, $disable) = explode($delimiter, $status);
    $enabled = explode("\n", $enabled);
    $modules = array();
    foreach ($enabled as $_enabled) {
        if (strstr($_enabled, 'Swissup_')) {
            $modules[] = $_enabled;
        }
    }
    $modules = array_filter($modules);
    $modules = array_unique($modules);
    $modules = implode(' ', $modules);
    if (!empty($modules)) {
        run("cd {{release_path}} && {{bin/magento}} module:disable $modules");
    }
})->setPrivate();

desc('Enabling magento 2 modules');
task('magento2:skeleton:modules:enable', function () {
    $modules = get('option_modules');
    // print_r($packages);
    if (empty($modules)) {
        $modules = '--all';
    } else {
        $modules = implode(' ', $modules);
    }
    // $status = run("cd {{release_path}} && {{bin/magento}} module:status");
    // $rm = 'None';
    // $status = str_replace($rm, '', $status);
    // $delimiter ='List of disabled modules:';
    // list($enable, $disable) = explode($delimiter, $status);
    // $rm = 'List of enabled modules:';
    // // $enable = str_replace($rm, '', $enable);
    // $modules = explode("\n", $disable);
    // $modules = array_filter($modules);

    $options = '';//' --clear-static-content --force ';
    run(
        "cd {{skeleton_path}} "
        . "&& {{bin/magento}} module:enable $options $modules"
        . "&& {{bin/magento}} setup:upgrade"
    );
})->setPrivate();

desc('Magento 2 after installation configuration (cache clean, set pass)');
task('magento2:skeleton:post:install', function () {
    //update modules
    // run(
    //     "cd {{skeleton_path}} "
    //     . "&& {{bin/composer}} update {{composer_params}} "
    //     . "&& {{bin/magento}} setup:upgrade"
    // );
    $commands = array(
        "{{bin/magento}} indexer:set-mode schedule",
        "{{bin/magento}} indexer:reindex",
        "{{bin/magento}} cache:clean",
        "{{bin/magento}} cache:flush",
        "{{bin/magento}} cron:run",
        // "{{bin/magento}} deploy:mode:set production",
        "{{bin/magento}} setup:di:compile",
        "{{bin/magento}} setup:static-content:deploy -f"
    );
    foreach ($commands as $command) {
        run("cd {{skeleton_path}} && " . $command);
    }
})->setPrivate();

desc('Show after installation success info');
task('magento2:skeleton:success', function () {

    $baseUrl = get('base_url');
    $releasePath = get('skeleton_path');
    $release = basename($releasePath);
    $databaseName = get('database_name');
    $password = get('admin_password');

    writeln("Dir : <comment>$releasePath</comment>");
    writeln("Db  : <comment>$databaseName</comment>");
    writeln("Url : <comment>$baseUrl/releases/$release</comment>");
    writeln("Admin Url : <comment>$baseUrl/releases/$release/index.php/admin admin $password</comment>");

    run("if [ -h {{skeleton_path}} ]; then rm {{skeleton_path}}; fi");
})->setPrivate();

desc('Show after full installation success info');
task('magento2:skeleton:rm', function () {
    $releasePath = get('deploy_path') . '/skeleton';
    run("if [ -h $releasePath ]; then rm $releasePath; fi");
})->setPrivate();

//dep magento2:skeleton:summon --modules=Swissup_Core,Swissup_Askit
desc("Combine task for install packages in magento 2 skeleton");
task('magento2:skeleton:summon', [
    'magento2:skeleton:check',
    'magento2:skeleton:set',
    'magento2:skeleton:modules:enable',
    'magento2:skeleton:post:install',
    'magento2:skeleton:maintenance:disable',
    'magento2:skeleton:success',
    'magento2:skeleton:rm'
]);

//dep magento2:skeleton:create --packages=swissup/*
desc('Check magento 2 store and create new if need');
task('magento2:skeleton:create', function () {
    $releases = get('magento2_skeleton_list');
    $store = get('releases_store');
    if (count($releases) >= $store) {
        die;
    }
});

desc('Deploy full magento 2 demo for magento2:modules');
task('magento2:skeleton:prepare', [
    'magento2:release:check',
    'deploy:prepare',
    'magento2:release:deploy',
    'magento2:release:git:clone',
    'deploy:shared',
    'magento2:release:auth_json',
    'magento2:release:composer:stability_dev',
    'magento2:release:composer:install',
    'magento2:release:create:db',
    'magento2:release:setup:install',
    'magento2:release:sampledata:install',
    'magento2:release:packages:install',
    'magento2:release:modules:disabled:all',
    'magento2:release:post:install',
    'magento2:release:maintenance:enable',
    'magento2:usermod',
    'magento2:release:permissions',
    'magento2:release:success',
    'magento2:release:deploy:symlink'
])->setPrivate();

after('magento2:skeleton:create', 'magento2:skeleton:prepare');

desc('List all potential skeleton target');
task('magento2:skeleton:list', function () {
    $releases = get('magento2_skeleton_list');
    if (!empty($releases)) {
        $releases = implode("\n", $releases);
        writeln("$releases");
    }
});
