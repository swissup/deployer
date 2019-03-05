<?php
namespace Deployer;

require 'recipe/magento.php';

require_once CUSTOM_RECIPE_DIR . '/bin/composer.php';
require_once CUSTOM_RECIPE_DIR . '/bin/jq.php';
require_once CUSTOM_RECIPE_DIR . '/bin/magerun.php';
require_once CUSTOM_RECIPE_DIR . '/bin/mysql.php';
require_once CUSTOM_RECIPE_DIR . '/bin/sudo.php';
require_once CUSTOM_RECIPE_DIR . '/options/packages.php';
require_once CUSTOM_RECIPE_DIR . '/options/modules.php';

set('magento_sample_data', function () {
    $samplePath = 'magento-sample-data-1.9.1.0';
    if (test("[ ! -d {{deploy_path}}/$samplePath ]")) {
        run("mkdir -p {{deploy_path}}/$samplePath");
        run("cd {{deploy_path}} && wget https://raw.githubusercontent.com/Vinai/compressed-magento-sample-data/1.9.1.0/compressed-magento-sample-data-1.9.1.0.tgz");
        run(
            "cd {{deploy_path}} "
            . "&& tar -xf compressed-magento-sample-data-1.9.1.0.tgz "
            . " && rm -rf compressed-magento-sample-data-1.9.1.0.tgz"
        );
    }
    return $samplePath;
});

set('magento_sample_data_override_dirs', ['skin', 'media']);
set('magento_sample_data_sql_file', 'magento_sample_data_for_1.9.1.0.sql');

set('magento_repository_last_tag', function () {
    $repository = get('repository');
    // $branch = get('branch');
    // if (empty($branch)) {
    //     $branch = 'master';
    // }
    $branch = '';
    $tags = run("cd {{deploy_path}} && {{bin/git}} ls-remote --tags $repository $branch");
    $tags = explode("\n", $tags);
    // print_r($tags);
    $tag = end($tags);
    list(, $tag) = explode('refs/tags/', $tag);
    list($tag, ) = explode('-', $tag);

    return  $tag;
});

set('magento_releases_list', function () {
    run("if [ ! -d {{deploy_path}}/releases ]; then mkdir -p {{deploy_path}}/releases; fi");
    $list = run('ls {{deploy_path}}/releases');
    $list = explode("\n", $list);
    rsort($list);
    $list = array_filter($list);

    $repo = get('repository');
    $_list = array();
    foreach ($list as $release) {
        $releaseDir = "{{deploy_path}}/releases/{$release}";
        $remote = run("cd {$releaseDir} && if [ -d {$releaseDir}/.git ]; then {{bin/git}} remote -v; fi");
        if (false !== strpos($remote, $repo)) {
            $_list[] = $release;
        }
    }
    return $_list;
});

desc('Check "release" dir status');
task('magento:release:check', function () {
    date_default_timezone_set('America/New_York');
    if (test("[ -h {{deploy_path}}/release ]")) {
        $release = run("readlink {{deploy_path}}/release");
        $release = explode('/', $release);
        $release = end($release);
        $release = substr($release, 4);
        $release = strtotime($release);
        if (14400 > abs(time() - $release)) {
            writeln("<error>Resource 'release' is busy</error>");
            die;
        } else {
            writeln("<error>Resource 'release' is old and has been removed</error>");
            run("rm -rf {{deploy_path}}/release ");
        }
    }
})->setPrivate();

desc('Copys sample data (media)');
task('magento:release:sampledata:dir', function () {
    if (!get('add_sample_data')) {
        return;
    }
    $samplePath = get('magento_sample_data');

    $dirs = get('magento_sample_data_override_dirs');//['skin'];
    foreach ($dirs as $dir) {
    //Copy directory
        run("if [ -d $(echo {{deploy_path}}/$samplePath/$dir) ]; "
            . "then cp -rpf {{deploy_path}}/$samplePath/$dir {{release_path}}; fi");
    }
})->setPrivate();

desc('Set file permissions for magento');
task('magento:release:permission', function () {
    // $release = get('magento_release');
    // $releasePath = get('deploy_path') . "/releases/$release";
    run("{{bin/sudo}} chmod -Rf go+w {{release_path}}/media");
    run("{{bin/sudo}} find {{release_path}}/media -type l -exec chmod -R go+w {} \;");
    run("if [ ! -d {{release_path}}/app/etc ]; then mkdir -p {{release_path}}/app/etc; fi");
    run("touch {{release_path}}/var/.htaccess");
    run("cd {{release_path}} && chmod o+w var/.htaccess app/etc");
    run("cd {{release_path}} && chmod -R o+w var");
})->setPrivate();

desc('Install sql data to database');
task('magento:release:sampledata:db', function () {
    $releasePath = get('release_path');
    $release = basename($releasePath);
    // $databaseName = get('database_name');

    run("{{bin/mysql}} -Bse 'DROP DATABASE IF EXISTS {{database_name}};'");
    run("{{bin/mysql}} -Bse 'CREATE DATABASE {{database_name}};'");
    // writeln("<info> Success db create <comment>" . $databaseName . "</comment></info>");
    $samplePath = get('magento_sample_data');
    $sqlFile = get('magento_sample_data_sql_file');
    run("{{bin/mysql}} {{database_name}} < {{deploy_path}}/$samplePath/$sqlFile 1>&2");

    // writeln("<info> Success import sample data to  <comment>" . $databaseName . "</comment></info>");
})->setPrivate();

desc('Install magento (using n98-magerun install)');
task('magento:release:setup:install', function () {
    $releasePath = get('release_path');
    $release = basename($releasePath);

    $_options = [
        'noDownload'             => '',
        'forceUseDb'             => '',
        'dbHost'                 => get('mysql_host'),
        'dbUser'                 => get('mysql_user'),
        'dbPass'                 => get('mysql_pass'),
        'dbName'                 => get('database_name'),
        'installationFolder'     => get('release_path'),
        'useDefaultConfigParams' => 'yes',
        'baseUrl'                => get('base_url')
    ];

    $options = "";
    foreach ($_options as $key => $value) {
        $options .= ' --' . ('' === $value ? "$key" : "$key="
            . ('yes' === $value ? $value : "\"$value\""));
    }

    run("{{bin/magerun}} install $options");
})->setPrivate();

desc('Install tm packages (composer require)');
task('magento:release:packages:install', function () {
    $releasePath = get('release_path');
    $release = basename($releasePath);
    run(
        "cd {{release_path}} "
        . " && rm -f composer.lock composer.json"
        . " && {{bin/composer}} init -n  --name='tm/demo{$release}' --type='magento-module' -s dev"
        . " && {{bin/composer}} config repositories.firegento composer https://packages.firegento.com"
        . " && {{bin/composer}} config secure-http false"
        . " && {{bin/composer}} config repositories.tmhub composer https://tmhub.github.io/packages/"
        . " && {{bin/composer}} config discard-changes true"
    );

    run(
        "cd {{release_path}} "
        . " && mv -f composer.json composer.json.old"
        . " && {{bin/jq}} '.extra." . 'magentorootdir = "{{release_path}}"'
        . "' composer.json.old  " . "| sed -r 's/magentorootdir/magento-root-dir/g'" . "> composer.json"
        . " && mv -f composer.json composer.json.old"
        . " && {{bin/jq}} '.extra." . 'magentodeploystrategy = "symlink"'. "' composer.json.old "
        . "| sed -r 's/magentodeploystrategy/magento-deploystrategy/g'" . " > composer.json"
        . " && mv -f composer.json composer.json.old"
        . " && {{bin/jq}} '.extra." . 'magentoforce'. " = true' composer.json.old "
        . "| sed -r 's/magentoforce/magento-force/g'"
        . " > composer.json"
        . " && rm composer.json.old"
    );

    $packages = [
        // 'symfony/console:2.4',
        // 'magento-hackathon/composer-command-integrator:*',
        'magento-hackathon/magento-composer-installer:3.0.*',
        'inchoo/php7:2.1.1'
    ];
    foreach ($packages as $package) {
        run("cd {{release_path}} && {{bin/composer}} require -n --no-update --ignore-platform-reqs $package");
    }
    run("cd {{release_path}} && {{bin/composer}} update --prefer-stable --ignore-platform-reqs {{composer_params}}");

    $packages = get('option_packages');
    if (empty($packages)) {
        return;
    }
    foreach ($packages as $package) {
        run("cd {{release_path}} && {{bin/composer}} require -n --no-update $package");
    }

    run("cd {{release_path}} && {{bin/composer}} update --prefer-stable --ignore-platform-reqs {{composer_params}}");
})->setPrivate();

desc('Disabling all modules');
task('magento:release:modules:disable:all', function () {

    $json = run("cd {{release_path}} && {{bin/magerun}} dev:module:list --vendor=TM --format=json");
    $modules = json_decode($json, true);
    if (empty($modules)) {
        return;
    }
    $command = "cd {{release_path}}";
    $root = get('release_path');

    foreach ($modules as $module) {
        $moduleName = $module['Name'];
        $file = "$root/app/etc/modules/$moduleName.xml";
        run("if [ -f $file ]; then mv --force $file $file.off; fi ");
        // if [ ! -f ]; then fi
        // $command .= "&& {{bin/magerun}} dev:module:disable $moduleName";
    }
    run($command);
    // $packages = set('option_packages');
    // // print_r($packages);
    // if (empty($packages)) {
    //     $modules = '--all';
    // } else {
    //     $modules = '';
    //     foreach ($packages as $package) {
    //         if (empty($package)) {
    //             continue;
    //         }
    //         if (strstr($package, ':')) {
    //             list($package, $version) = explode(':', $package, 2);
    //         }
    //         list($_vendor, $_package) = explode('/', $package);
    //         $module =  //ucfirst($_vendor) .
    //             'TM_' . ucfirst($_package);
    //         print_r("cd {{release_path}} && sudo {{bin/magerun}} dev:module:disable $module");
    //         run("cd {{release_path}} && sudo {{bin/magerun}} dev:module:disable $module");
    //     }
    // }
})->setPrivate();

desc('Magento after installation configuration (cache clean, set pass)');
task('magento:release:post:install', function () {
    $releasePath = get('release_path');
    $release = basename($releasePath);

    $password = get('admin_password');
    run(
        "cd {{release_path}} "
        . " && {{bin/magerun}} sys:setup:run"
        // . " && {{bin/magerun}} dev:module:update"
        . " && {{bin/magerun}} admin:user:change-password admin $password"
        . " && {{bin/magerun}} dev:symlinks 0"
        . " && {{bin/magerun}} index:reindex:all"
        . " && {{bin/magerun}} cache:clean"
        . " && {{bin/magerun}} cache:flush"
        . " && {{bin/magerun}} admin:notifications"
        . " && cp errors/local.xml.sample errors/local.xml"
        . " && {{bin/magerun}} config:set web/seo/use_rewrites 0"
    );
})->setPrivate();

desc('Enable maintenance mode');
task('magento:release:maintenance:enable', function () {
    run("cd {{release_path}} && {{bin/magerun}} sys:maintenance --on");
})->setPrivate();

desc('Show after installation success info');
task('magento:release:success', function () {
    $releasePath = get('release_path');
    $release = basename($releasePath);
    $baseUrl = get('base_url');
    $databaseName = get('database_name');
    $password = get('admin_password');

    writeln("Dir : <comment>$releasePath</comment>");
    writeln("Db  : <comment>$databaseName</comment>");
    writeln("Url : <comment>$baseUrl</comment>");
    writeln("Admin Url : <comment>$baseUrl/index.php/admin admin $password</comment>");
})->setPrivate();

desc('Creating symlink to release');
task('magento:release:deploy:symlink', function () {
    // run("cd {{deploy_path}} && {{bin/symlink}} {{release_path}} current"); // Atomic override symlink.
    run("cd {{deploy_path}} && if [ -h release ]; then rm release; fi");
    // run("cd {{deploy_path}} && rm release"); // Remove release link.
})->setPrivate();

desc('List all magento releases');
task('magento:releases:list', function () {
    $releases = get('magento_releases_list');
    if (!empty($releases)) {
        $releases = implode("\n", $releases);
        writeln("$releases");
    }
});
