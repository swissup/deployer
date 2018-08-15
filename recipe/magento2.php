<?php
namespace Deployer;

require_once 'recipe/common.php';
// require_once 'recipe/magento2.php';
require_once __DIR__ . '/releases.php';
require_once __DIR__ . '/bin/composer.php';
require_once __DIR__ . '/bin/magento.php';
require_once __DIR__ . '/bin/magerun2.php';
require_once __DIR__ . '/bin/mysql.php';
require_once __DIR__ . '/bin/jq.php';
require_once __DIR__ . '/bin/sudo.php';
require_once __DIR__ . '/options/packages.php';
require_once __DIR__ . '/options/modules.php';

// Configuration
set('shared_files', [
    'app/etc/config.php',
    'app/etc/env.php',
    'var/.maintenance.ip',
]);
set('shared_dirs', [
    'var/log',
    'var/backups',
    'pub/media',
]);
set('writable_dirs', [
    'var',
    'pub/static',
    'pub/media',
]);
set('clear_paths', [
    'var/generation/*',
    'var/cache/*',
]);

set('magento2_repository', 'git@github.com:magento/magento2.git');
// set('writable_dirs', ['var']);

set('magento2_repository_last_tag', function () {
    $repository = get('magento2_repository');
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

set('magento2_releases_list', function () {
    run("if [ ! -d {{deploy_path}}/releases ]; then mkdir -p {{deploy_path}}/releases; fi");
    $list = run("ls {{deploy_path}}/releases");
    $list = explode("\n", $list);
    rsort($list);

    $repo = get('magento2_repository');
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

// set('httpuser', 'apache');
set('httpuser', function () {
    return run("ps aux | grep -E '[a]pache|[h]ttpd|[_]www|[w]ww-data|[n]ginx' "
        . "| grep -v root | head -1 | cut -d\  -f1");
});

set('owner', function () {
    // return getenv('USER');
    return run('whoami');
    return run("ls -ld {{release_path}} | awk '{print $3}'");
});

task('magento2:repository:last:tag', function () {
    $tag = get('magento2_repository_last_tag');
    writeln("$tag");
})->setPrivate();

task('magento2:release:check', function () {
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

desc('Prepare magento 2 release place');
task('magento2:release:deploy', function () {
    if (input()->hasOption('release') && !empty(input()->getOption('release'))) {
        $release = input()->getOption('release');
    } else {
        if (input()->hasOption('tag')) {
            $tag = input()->getOption('tag');
            if (!empty($tag)) {
                $tag = str_replace('.', '', $tag);
                $tag = str_pad($tag, 4, '0');
            }
        }
        if (empty($tag)) {
            $tag = get('magento2_repository_last_tag');
        }
        $tag = str_replace('.', '', $tag);
        $tag = str_pad($tag, 4, '0');

        $release = $tag . date('YmdHis');
    }
    $release = preg_replace("/[^A-Za-z0-9 ]/", '', $release);

    $releasePath = "{{deploy_path}}/releases/$release";
    $i = 0;
    // while (is_dir(env()->parse($releasePath)) && $i < 42) {
    while (is_dir($releasePath) && $i < 42) {
        $releasePath .= '.' . ++$i;
    }
    run("mkdir $releasePath");
    run("cd {{deploy_path}} && if [ -h release ]; then rm release; fi");
    run("cd {{deploy_path}} && {{bin/symlink}} $releasePath release");
    set('release_path', $releasePath);
})->setPrivate();

/**
 * Update project code
 */
desc('Updating code magento 2 code (git clone)');
task('magento2:release:git:clone', function () {
    $repository = get('magento2_repository');
    $gitCache = get('git_cache');
    $depth = $gitCache ? '' : '--depth 1';
    $branch = get('branch');
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
        $tag = get('magento2_repository_last_tag');
        $at = "-b $tag";
    }
    $releases = get('magento2_releases_list');
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
        run("{{bin/git}} clone $at $depth --recursive -q $repository {{release_path}} 2>&1");
    }
    if (!empty($tag)) {
        run("cd {{release_path}} && {{bin/git}} checkout $tag");
    }
    run("cd {{release_path}} && {{bin/git}} config core.fileMode false");
});//->setPrivate();

desc('Create auth.json if not exist and add repo.magento.com credentials');
task('magento2:release:auth_json', function () {

    // $username = get('magento_composer_username');
    // $password = get('magento_composer_password');
    $username = runLocally('{{bin/composer}} global config http-basic.repo.magento.com.username');
    $password = runLocally('{{bin/composer}} global config http-basic.repo.magento.com.password');
    // run(
    //     "if [ ! -f {{auth.json}} ]; then " .
    //     "echo '{}' > {{auth.json}}.old; " .
    //     "{{bin/jq}} '.httpbasic.repomagentocom = {\"username\":\"$username\", \"password\":\"$password\"}' {{auth.json}}.old" .
    //     "| sed -r 's/httpbasic/http-basic/g'" .
    //     "| sed -r 's/repomagentocom/repo.magento.com/g'" .
    //     "> {{auth.json}};" .
    //     "rm {{auth.json}}.old; " .
    //     "fi"
    // );
    run(
        "cd {{release_path}} "
        . " && {{bin/composer}} config http-basic.repo.magento.com $username $password"
    );
    run(
        "cd {{release_path}} "
        . "&& {{bin/composer}} config repositories.0 composer https://repo.magento.com"
    );

    // run("cp {{auth.json}} {{release_path}} ");
    // writeln(run("cat {{auth.json}}"));
})->setPrivate();

desc('Run composer install command in current mage 2 instance');
task('magento2:release:composer:install', function () {
    run("cd {{release_path}} && {{bin/composer}} install {{composer_params}}");
})->setPrivate();

desc('Add file owner to apache group and back');
task('magento2:usermod', function () {
    $group = get('httpuser');
    $user = get('owner');
    run("{{bin/sudo}} usermod -a -G $group $user");
    run("{{bin/sudo}} usermod -a -G $user $group");
})->setPrivate();

desc('Set file permissions for current magento 2 instance');
task('magento2:release:permissions', function () {
    $user = get('owner');
    $group = get('httpuser');
    run(
        "cd {{release_path}} "
        . "&& {{bin/sudo}} chown -R $user:$group . "
        . "&& {{bin/sudo}} find . -type d -exec chmod 770 {} \; "
        . "&& {{bin/sudo}} find . -type f -exec chmod 660 {} \; "
        // . "&& {{bin/sudo}} find . -type l -exec chmod 660 {} \; "
        . "&& {{bin/sudo}} chmod u+x {{bin/magento}} "
        // . "&& {{bin/sudo}} chmod g+x {{bin/magento}} "
        // . "&& {{bin/sudo}} chmod -R g+w {app/etc,pub,var,vendor}"
        . "&& {{bin/sudo}} chmod -R g+w app/etc "
        . "&& {{bin/sudo}} chmod -R g+w pub "
        . "&& {{bin/sudo}} chmod -R g+w var "
    );
})->setPrivate();

desc('Create database for magento 2 instance');
task('magento2:release:create:db', function () {
    run("{{bin/mysql}} -Bse 'DROP DATABASE IF EXISTS {{database_name}};'");
    run("{{bin/mysql}} -Bse 'CREATE DATABASE {{database_name}};'");
})->setPrivate();

desc('Install magerun 2 (bin/magento setup:install)');
task('magento2:release:setup:install', function () {
    // $releasePath = get('release_path');
    // $release = basename($releasePath);
    $databaseName = get('database_name');
    $adminPassword = get('admin_password');

    $_options = [
        'admin-firstname'   => 'John',
        'admin-lastname'    => 'Doe',
        'admin-email'       => 'john.doe@gmail.com',
        'admin-user'        => 'admin',
        'admin-password'    => $adminPassword,//uniqid($release),
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
    run("cd {{release_path}} && {{bin/magento}} setup:install $options");
})->setPrivate();

desc('Set composer minimum-stability="dev"');
task('magento2:release:composer:preinstall', function () {
    run(
        "cd {{release_path}} "
        . "&& mv -f composer.json composer.json.old"
        . "&& {{bin/jq}} '." .'"minimum-stability"="dev"' ."' composer.json.old "
        // ."| sed -r 's/\"minimum-stability\"/minimum-stability/g'"
        .  "> composer.json "
        . " && rm composer.json.old"
    );
})->setPrivate();

// before('magento2:release:composer:install', 'magento2:release:composer:preinstall');
// before('magento2:release:sampledata:install', 'magento2:release:composer:preinstall');
//
task('magento2:release:setup:upgrade', function () {
    run("cd {{release_path}} && {{bin/magento}} setup:upgrade");
})->setPrivate();

desc('Install magento 2 sampledata ');
task('magento2:release:sampledata:install', function () {

    if (!get('add_sample_data')) {
        return;
    }
    // run("cd {{release_path}} && {{bin/magento}} sampledata:deploy", [
    //     'timeout' => 1000
    // ]);
    // run("cd {{release_path}} && {{bin/magento}} setup:upgrade", [
    //     'timeout' => 600
    // ]);
    // return;

    run(
        "if [ ! -d {{deploy_path}}/magento2-sample-data ]; then  "
        . "cd {{deploy_path}};"
        . "{{bin/git}} clone git@github.com:magento/magento2-sample-data.git;"
        . "{{bin/sudo}} chown -R :{{httpuser}} magento2-sample-data ;"
        . "{{bin/sudo}} find magento2-sample-data -type d -exec chmod g+ws {} \; ;"
        . " fi"
    );
    run("cd {{deploy_path}}/magento2-sample-data && {{bin/git}} fetch && {{bin/git}} checkout ");

    if (input()->hasOption('tag')) {
        $tag = input()->getOption('tag');
    }
    if (empty($tag)) {
        $tag = get('magento2_repository_last_tag');
    }

    if (!empty($tag)) {
        run("cd {{deploy_path}}/magento2-sample-data && {{bin/git}} checkout $tag");
    }
    run(
        "php -f {{deploy_path}}/magento2-sample-data/dev/tools/build-sample-data.php -- "
        . "--ce-source=\"{{release_path}}\""
    );

    run("cd {{release_path}} && {{bin/sudo}} rm -rf cache/* page_cache/* generation/*");
    run("cd {{release_path}} && {{bin/magento}} setup:upgrade", [
        'timeout' => 600
    ]);
    if (!empty($tag)) {
        run("cd {{deploy_path}}/magento2-sample-data && {{bin/git}} checkout ");
    }
})->setPrivate();

desc('Install swissup packages (composer require)');
task('magento2:release:packages:install', function () {
    $packages = get('option_packages');
    if (empty($packages)) {
        return;
    }
    run(
        "cd {{release_path}} "
        . " && {{bin/composer}} config repositories.swissup composer https://docs.swissuplabs.com/packages/"
    );
    foreach ($packages as $package) {
        if (empty($package)) {
            continue;
        }
        // list($_package, $version) = explode(':', $package, 2);
        run(
            "cd {{release_path}} "
            // . " && {{bin/composer}} config repositories.$_package vcs git@github.com:$_package.git"
            . " && {{bin/composer}} require -n --no-update --ignore-platform-reqs $package"
        );
    }

    $packages = implode(' ', $packages);
    $packages = str_replace(':*', '', $packages);

    run("cd {{release_path}} && {{bin/composer}} update $packages --ignore-platform-reqs {{composer_params}}", [
        'timeout' => 600
    ]);
    // run("cd {{release_path}} && {{bin/composer}} update {{composer_params}}");
    run("cd {{release_path}} && {{bin/magento}} setup:upgrade");
})->setPrivate();

desc('Magento 2 after installation configuration (cache clean, set pass)');
task('magento2:release:post:install', function () {
    $commands = array(
        "{{bin/magento}} deploy:mode:set developer",
        "{{bin/magento}} setup:di:compile",
        "{{bin/composer}} dump-autoload -o",
        "{{bin/magento}} setup:static-content:deploy -f en_US",
        "{{bin/magento}} indexer:set-mode schedule",
        "{{bin/magento}} indexer:reindex",
        "{{bin/magento}} cache:clean",
        "{{bin/magento}} cache:flush",
        "{{bin/magento}} cron:run",
        "{{bin/magento}} config:set dev/static/sign 0 --lock-env",
        "{{bin/magento}} cache:clean config"
    );
    foreach ($commands as $command) {
        run("cd {{release_path}} && " . $command, [
            'timeout' => 1200
        ]);
    }
})->setPrivate();

desc('Enable magento 2 maintenance');
task('magento2:release:maintenance:enable', function () {
    run("cd {{release_path}} && {{bin/magento}} maintenance:enable");
})->setPrivate();

desc('Disable magento 2 maintenance');
task('magento2:release:maintenance:disable', function () {
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

desc('Show after installation success info');
task('magento2:release:success', function () {
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
task('magento2:release:deploy:symlink', function () {
    // run("cd {{deploy_path}} && {{bin/symlink}} {{release_path}} current"); // Atomic override symlink.
    run("cd {{deploy_path}} && if [ -h release ]; then rm release; fi");
    // run("cd {{deploy_path}} && rm release"); // Remove release link.
})->setPrivate();

desc('List all magento 2 releases');
task('magento2:releases:list', function () {
    $releases = get('magento2_releases_list');
    if (!empty($releases)) {
        $releases = implode("\n", $releases);
        writeln("$releases");
    }
});

task('magento2:init:failed', function () {
    if (test("[ -h {{deploy_path}}/release ]")) {
        $releasePath = get('release_path');
        $release = basename($releasePath);
        $databaseName = get('database_name');

        // run("cd {{deploy_path}} && if [ -e release ]; then rm release; fi");

        if (test("[ -d {{deploy_path}}/releases/$release ]")) {
            run("{{bin/sudo}} rm -rf {{deploy_path}}/releases/$release");
        }
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
    // 'magento2:release:check',
    'deploy:prepare',
    'deploy:lock',
    'magento2:release:deploy', //'deploy:release',
    'magento2:release:git:clone', //'deploy:update_code',
    'magento2:release:auth_json',
    'magento2:release:composer:preinstall',
    'magento2:release:composer:install',
    'magento2:release:create:db',
    'magento2:release:setup:install',
    'magento2:release:setup:upgrade',
    'magento2:release:sampledata:install',
    'magento2:release:packages:install',
    'magento2:release:post:install',
    'magento2:usermod',
    'magento2:release:permissions',
    // 'magento2:app:config:dump',
    'deploy:shared',
    'deploy:writable',
    'deploy:symlink',
    'deploy:unlock',
    // 'cleanup',
    'magento2:release:success'
    // 'magento2:release:deploy:symlink'
])->once();

fail('magento2:init', 'magento2:init:failed');

desc('Deploy magento2');
task('magento2:deploy', [
    'deploy:info',
    'magento2:release:check',
    'deploy:prepare',
    'deploy:lock',
    'magento2:release:deploy', // 'deploy:release',
    'magento2:release:git:clone',
    'deploy:shared',
    'magento2:release:auth_json',
    'magento2:release:composer:preinstall',
    'magento2:release:composer:install',
    'magento2:release:maintenance:enable',
    // 'magento2:app:config:import',
    'magento2:release:setup:upgrade',
    'magento2:release:packages:install',
    'magento2:release:post:install',
    'magento2:release:permissions',
    // 'magento2:app:config:dump',
    'magento2:release:maintenance:disable',
    'deploy:writable',
    'deploy:clear_paths',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
    'magento2:release:success'
]);
fail('magento2:deploy', 'magento2:init:failed');
