<?php
namespace Deployer;

require_once 'recipe/common.php';
require_once __DIR__ . '/bin/composer.php';
require_once __DIR__ . '/bin/magento.php';
require_once __DIR__ . '/bin/mysql.php';
require_once __DIR__ . '/bin/jq.php';
require_once __DIR__ . '/bin/sudo.php';
require_once __DIR__ . '/options/packages.php';
require_once __DIR__ . '/options/modules.php';

set('use_relative_symlink', false);
set('shared_dirs', []);
set('shared_files', []);
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
    $tag = str_replace('.', '', $tag);
    $tag = str_pad($tag, 4, '0');
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
    $release = $tag . date('YmdHis');
    $releasePath = "{{deploy_path}}/releases/$release";
    $i = 0;
    // while (is_dir(env()->parse($releasePath)) && $i < 42) {
    while (is_dir($releasePath) && $i < 42) {
        $releasePath .= '.' . ++$i;
    }
    // writeln($releasePath);
    run("mkdir $releasePath");
    run("cd {{deploy_path}} && if [ -h release ]; then rm release; fi");
    run("cd {{deploy_path}} && ln -nfs $releasePath release");
    //@todo don't use release symlink just set('release_path', $releasePath)
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
})->setPrivate();

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
    $releasePath = get('release_path');
    $release = basename($releasePath);
    // $databaseName = 'db' . $release;
    run("{{bin/mysql}} -Bse 'DROP DATABASE IF EXISTS db$release;'");
    run("{{bin/mysql}} -Bse 'CREATE DATABASE db$release;'");
})->setPrivate();

desc('Install magerun 2 (bin/magento setup:install)');
task('magento2:release:setup:install', function () {
    $releasePath = get('release_path');
    $release = basename($releasePath);
    $baseUrl = get('base_url');

    $_options = [
        'admin-firstname'   => 'John',
        'admin-lastname'    => 'Doe',
        'admin-email'       => 'john.doe@gmail.com',
        'admin-user'        => 'admin',
        'admin-password'    => 'db' . $release,//uniqid($release),
        'base-url'          => "$baseUrl/releases/$release",
        'backend-frontname' => 'admin',
        'db-host'           => '127.0.0.1',
        'db-name'           => 'db' . $release,
        'db-user'           => get('mysql_user'),
        'db-password'       => get('mysql_pass'),
        'language'          => 'en_US',
        'currency'          => 'USD',
        'timezone'          => 'America/Chicago',
        'use-rewrites'      => '0',
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
task('magento2:release:composer:stability_dev', function () {
    run(
        "cd {{release_path}} "
        . "&& mv -f composer.json composer.json.old"
        . "&& {{bin/jq}} '." .'"minimum-stability"="dev"' ."' composer.json.old "
        // ."| sed -r 's/\"minimum-stability\"/minimum-stability/g'"
        .  "> composer.json "
        . " && rm composer.json.old"
    );
})->setPrivate();

// before('magento2:release:composer:install', 'magento2:release:composer:stability_dev');
// before('magento2:release:sampledata:install', 'magento2:release:composer:stability_dev');
//
task('magento2:release:setup:upgrade', function () {
    run("cd {{release_path}} && {{bin/magento}} setup:upgrade");
})->setPrivate();

desc('Install magento 2 sampledata ');
task('magento2:release:sampledata:install', function () {

    // run("cd {{release_path}} && {{bin/magento}} sampledata:deploy && {{bin/magento}} setup:upgrade");
    // return;

    run(
        "if [ ! -d {{deploy_path}}/magento2-sample-data ]; then  "
        . "cd {{deploy_path}};"
        . "{{bin/git}} clone git@github.com:magento/magento2-sample-data.git;"
        . " fi"
    );
    if (input()->hasOption('tag')) {
        $tag = input()->getOption('tag');
    }
    if (!empty($tag)) {
        run("cd {{deploy_path}}/magento2-sample-data && {{bin/git}} pull origin develop && {{bin/git}} checkout $tag");
    }
    run(
        "php -f {{deploy_path}}/magento2-sample-data/dev/tools/build-sample-data.php -- "
        . "--ce-source=\"{{release_path}}\""
    );
    run("cd {{release_path}} && {{bin/magento}} setup:upgrade", [
        'timeout' => 600
    ]);
    if (!empty($tag)) {
        run("cd {{deploy_path}}/magento2-sample-data && {{bin/git}} checkout develop");
    }
    return;
    // $packages = [
    //     "magento/module-bundle-sample-data",
    //     "magento/module-catalog-rule-sample-data",
    //     "magento/module-catalog-sample-data",
    //     "magento/module-cms-sample-data",
    //     "magento/module-configurable-sample-data",
    //     "magento/module-customer-sample-data",
    //     "magento/module-downloadable-sample-data",
    //     "magento/module-grouped-product-sample-data",
    //     "magento/module-msrp-sample-data",
    //     "magento/module-offline-shipping-sample-data",
    //     "magento/module-product-links-sample-data",
    //     "magento/module-review-sample-data",
    //     "magento/module-sales-rule-sample-data",
    //     "magento/module-sales-sample-data",
    //     "magento/module-sample-data",
    //     "magento/module-swatches-sample-data",
    //     "magento/module-tax-sample-data",
    //     "magento/module-theme-sample-data",
    //     "magento/module-widget-sample-data",
    //     "magento/module-wishlist-sample-data"
    // ];
    // $version = ':~100.0.1';
    // foreach ($packages as $package) {
    //     run("cd {{release_path}} && {{bin/composer}} require -n --no-update $package$version");
    // }
    // writeln(run(
    //     "cd {{release_path}} "
    //     . "&& {{bin/composer}} update {{composer_params}}"
    //     . "&& {{bin/magento}} setup:upgrade"
    // ));
    // return;
    // run(
    //     "cd {{release_path}} "
    //     . "&& {{bin/composer}} update {{composer_params}}"
    //     . "&& {{bin/magento}} sampledata:deploy "
    // );
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

desc('Magento 2 after installation configuration (cache clean, set pass)');
task('magento2:release:post:install', function () {
    $commands = array(
        "{{bin/magento}} deploy:mode:set developer",
        "{{bin/magento}} setup:di:compile",
        "{{bin/magento}} setup:static-content:deploy -f",
        "{{bin/magento}} indexer:set-mode schedule",
        "{{bin/magento}} indexer:reindex",
        "{{bin/magento}} cache:clean",
        "{{bin/magento}} cache:flush",
        "{{bin/magento}} cron:run",
        "{{bin/magento}} config:set dev/static/sign 0 ",
        "{{bin/magento}} cache:clean config"
    );
    foreach ($commands as $command) {
        run("cd {{release_path}} && " . $command, [
            'timeout' => 600
        ]);
    }
})->setPrivate();

desc('Enable magento 2 maintenance');
task('magento2:release:maintenance:enable', function () {
    run("cd {{release_path}} && {{bin/magento}} maintenance:enable");
})->setPrivate();

desc('Show after installation success info');
task('magento2:release:success', function () {
    $releasePath = get('release_path');
    $release = basename($releasePath);
    $baseUrl = get('base_url');
    $releasePath = get('release_path');
    writeln("Dir : <comment>$releasePath</comment>");
    writeln("Db  : <comment>db$release</comment>");
    writeln("Url : <comment>$baseUrl/releases/$release</comment>");
    writeln("Admin Url : <comment>$baseUrl/releases/$release/index.php/admin admin db$release</comment>");
})->setPrivate();

desc('Creating symlink to release');
task('magento2:release:deploy:symlink', function () {
    // run("cd {{deploy_path}} && ln -nfs {{release_path}} current"); // Atomic override symlink.
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

/**
 * Main task
 *  magento2:create --packages=swissup/ajaxpro,swissup/ajaxlayerednavigation,swissup/firecheckout,swissup/askit,swissup/testimonials,swissup/sold-together,swissup/rich-snippets,swissup/reviewreminder,swissup/pro-labels,swissup/highlight,swissup/fblike,swissup/easytabs,swissup/easy-slide,swissup/easyflags,swissup/easycatalogimg,swissup/easybanner,swissup/attributepages,swissup/ajaxsearch,swissup/address-field-manager -vv
 */
desc('Create new magento2 demo. Options --packages=[], --tag=[]');
task('magento2:create', [
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
    'magento2:release:setup:upgrade',
    'magento2:release:sampledata:install',
    'magento2:release:packages:install',
    'magento2:release:post:install',
    'magento2:usermod',
    'magento2:release:permissions',
    'magento2:release:success',
    'magento2:release:deploy:symlink'
]);
