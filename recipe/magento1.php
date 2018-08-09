<?php
namespace Deployer;

require 'recipe/magento.php';

require_once __DIR__ . '/bin/composer.php';
require_once __DIR__ . '/bin/jq.php';
require_once __DIR__ . '/bin/magerun.php';
require_once __DIR__ . '/bin/mysql.php';
require_once __DIR__ . '/bin/sudo.php';
require_once __DIR__ . '/options/packages.php';
require_once __DIR__ . '/options/modules.php';

set('use_relative_symlink', false);
set('shared_dirs', []);
set('shared_files', []);
set('magento_repository', 'git@github.com:OpenMage/magento-mirror.git');
// set('magento_repository', 'git@github.com:colinmollenhour/magento-lite.git');
// set('magento_repository', 'git@github.com:speedupmate/Magento-CE-Mirror.git');

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
    $repository = get('magento_repository');
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

    $repo = get('magento_repository');
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

desc('Prepare magento release place');
task('magento:release:deploy', function () {
    if (input()->hasOption('release')) {
        $release = input()->getOption('release');
    } else {
        if (input()->hasOption('tag')) {
            $tag = input()->getOption('tag');
        }
        if (empty($tag)) {
            $tag = get('magento_repository_last_tag');
        }
        $tag = str_replace('.', '', $tag);
        $tag = str_pad($tag, 4, '0');

        $release = $tag . date('YmdHis');
    }
    $release = preg_replace("/[^A-Za-z0-9 ]/", '', $release);

    $releasePath = "{{deploy_path}}/releases/$release";

    run("mkdir $releasePath");
    run("cd {{deploy_path}} && if [ -h release ]; then rm release; fi");
    run("cd {{deploy_path}} && {{bin/symlink}} $releasePath release");
})->setPrivate();

/**
 * Update project code
 */
desc('Updating code (git clone)');
task('magento:release:git:clone', function () {
    $repository = get('magento_repository');
    $gitCache = get('git_cache');
    $depth = $gitCache ? '' : '--depth 1';

    if (input()->hasOption('branch')) {
        $branch = input()->getOption('branch');
    }
    if (input()->hasOption('tag')) {
        $tag = input()->getOption('tag');
    }
    $at = '';
    if (!empty($tag)) {
        $at = "-b $tag";
    } elseif (!empty($branch)) {
        $at = "-b $branch";
    } else {
        $tag = get('magento_repository_last_tag');
        $at = "-b $tag";
    }

    $releases = get('magento_releases_list');
    if ($gitCache && isset($releases[1])) {
        try {
            run(
                "{{bin/git}} clone $at --recursive -q --reference {{deploy_path}}/releases/{$releases[1]} --dissociate "
                . "$repository  {{release_path}} 2>&1"
            );
        } catch (RuntimeException $exc) {
            run("{{bin/git}} clone $at --recursive -q $repository {{release_path}} 2>&1");
        }
    } else {
        //run("{{bin/git}} clone $at $depth --recursive -q $repository {{release_path}} 2>&1");
        run("{{bin/git}} clone -q $repository {{release_path}} 2>&1");
    }
    if (!empty($tag)) {
        run("cd {{release_path}} && {{bin/git}} checkout $tag");
    }
    run("cd {{release_path}} && {{bin/git}} config core.fileMode false");
})->setPrivate();

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
    $id = $release;
    $databaseName = 'db' . $id;
    run("{{bin/mysql}} -Bse 'DROP DATABASE IF EXISTS $databaseName;'");
    run("{{bin/mysql}} -Bse 'CREATE DATABASE $databaseName;'");
    // writeln("<info> Success db create <comment>" . $databaseName . "</comment></info>");
    $samplePath = get('magento_sample_data');
    $sqlFile = get('magento_sample_data_sql_file');
    run("{{bin/mysql}} $databaseName < {{deploy_path}}/$samplePath/$sqlFile 1>&2");

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
        'dbName'                 => 'db' . $release,
        'installationFolder'     => get('release_path'),
        'useDefaultConfigParams' => 'yes',
        'baseUrl'                => get('base_url') . "/releases/$release"
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
    run(
        "cd {{release_path}} "
        . " && {{bin/magerun}} sys:setup:run"
        // . " && {{bin/magerun}} dev:module:update"
        . " && {{bin/magerun}} admin:user:change-password admin db{$release}"
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

desc('Show after installation success info');
task('magento:release:success', function () {
    $baseUrl = get('base_url');
    $releasePath = get('release_path');
    $release = basename($releasePath);

    writeln("Dir : <comment>$releasePath</comment>");
    writeln("Db  : <comment>db$release</comment>");
    writeln("Url : <comment>$baseUrl/releases/$release</comment>");
    writeln("Admin Url : <comment>$baseUrl/releases/$release/index.php/admin admin db$release</comment>");
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

task('magento:create:failed', function () {
    $releasePath = get('release_path');
    $release = basename($releasePath);
    // run("cd {{deploy_path}} && if [ -e release ]; then rm release; fi");
    run("cd {{deploy_path}} && if [ -h release ]; then rm release; fi");
    run("{{bin/sudo}} rm -rf {{deploy_path}}/releases/$release");
    run("{{bin/mysql}} -Bse 'DROP DATABASE IF EXISTS db$release;'");
})->setPrivate();

/**
 * Main task
 * dep6 magento:create --packages=tm/ajax-pro:\*,tm/ajax-layered-navigation:\*,tm/ajax-search:\*,tm/ask-it:\*,tm/easy-banner:\*,tm/helpdesk:\*,tm/navigation-pro:\*,tm/cache:\*,tm/highlight:\*,tm/pro-labels:\*,tm/review-reminder:\*,tm/sold-together:\*
 */
desc('Create magento demo');
desc('Create new magento demo. Options --packages=[], --tag=[]');
task('magento:create', [
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
    'magento:release:success',
    'magento:release:deploy:symlink'
]);

fail('magento:create', 'magento:create:failed');
