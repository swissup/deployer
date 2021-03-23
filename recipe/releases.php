<?php

namespace Deployer;

require_once CUSTOM_RECIPE_DIR . '/bin/sudo.php';

set('database_name_prefix', function () {
    return 'database' . hash('crc32', get('servername'));
    // return 'db';
    // return 'db_' . preg_replace("/[^A-Za-z0-9 ]/", '_', get('servername')) . '_';
});

desc('Show path to current release');
task('release:path', function () {
    writeln(get('release_path'));
});

set('release', function () {
    $path = get('release_path');//str_replace("\n", '', run("readlink {{deploy_path}}/release"));
    return basename($path); //{{deploy_path}}/release
});

desc("Show current release (clone task current)");
task('release:current', function () {
    $release = get('release');
    writeln("$release");
});

// task('release:current:dir', function () {
//     $r = get('magento_root');
//     writeln("$r");
// })->desc("Show current magento root");

option(
    'release',
    null,
    \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL,
    'Release to set as current.'
);

desc("Change current release by option value");
task('release:set', function () {
    $release = input()->getOption('release');
    if (empty($release)) {
        writeln("<error>That task required option --release=[RELEASE]</error>");
        writeln("Use default: {{release_path}}");
    } else {
        $list = get('releases_list_all');
        if (in_array($release, $list)) {
            $releaseDir = get('deploy_path') . "/releases/$release";
            // run("cd {{deploy_path}} && {{bin/symlink}} $releaseDir current");
            set('release_path', $releaseDir);
        } else {
            writeln("<error>Wrong release option value $release</error>");
        }
    }
});

set('releases_list_all', function () {
    run("if [ ! -d {{deploy_path}}/releases ]; then mkdir -p {{deploy_path}}/releases; fi");
    // $list = run("ls {{deploy_path}}/releases")->toArray();
    $list = explode("\n", run("ls {{deploy_path}}/releases"));
    rsort($list);
    return array_filter($list);
});

desc("List all releases");
task('releases:list', function () {
    $list = get('releases_list_all');

    $list = array_filter($list);
    if (!empty($list)) {
        $releases = implode("\n", $list);
        writeln("$releases");
    }
    writeln(count($list));
});

set('get_all_databases', function () {
    $dbs = explode("\n", run("{{bin/mysql}} -Bse 'show databases'"));
    return $dbs;
});

desc("List all databases");
task('releases:list:db', function () {
    $dbs = get('get_all_databases');
    foreach ($dbs as $db) {
        if (0 === strpos($db, get('database_name_prefix'))) {
            writeln("$db");
        }
    }
});//->setPrivate();

/**
 * Cleanup
 */
desc('Cleaning up old releases first ' . get('keep_releases'));
task('releases:remove:old', function () {
    $releases = get('releases_list_all');
    $keep = get('keep_releases');

    $releases = array_slice($releases, $keep);
    $dbs = get('get_all_databases');
    foreach ($releases as $release) {
        run("{{bin/sudo}} rm -rf {{deploy_path}}/releases/$release");
        $db = get('database_name_prefix') . $release;
        if (in_array($db, $dbs)) {
            run("{{bin/mysql}} -Bse 'drop database $db'");
        }
    }
});

desc("Remove old releases and databases [3 days]");
task('releases:remove:oldest', function () {
    $days = 3;
    $deadtime = (date('Ymd') - $days) * 1000000;
    $releases = get('releases_list_all');

    $dbs = get('get_all_databases');
    foreach ($releases as $release) {
        $_release = (int) substr($release, 4);
        // $_release = (int) $_release;
        // writeln($release);
        // writeln($_release);
        // writeln($deadtime);
        // writeln(($deadtime > $_release ? 'true' : 'false')) ;
        if ($deadtime > $_release) {
            run("{{bin/sudo}} rm -rf {{deploy_path}}/releases/$release");
            $db = get('database_name_prefix') . $release;
            if (in_array($db, $dbs)) {
                run("{{bin/mysql}} -Bse 'drop database $db'");
            }
        }
    }
});

desc("Remove all releases and databases");
task('releases:remove:all', function () {
    run("cd {{deploy_path}} && {{bin/sudo}} rm -rf releases/* current shared ");
});

desc("Remove skeleton and release dirs");
task('releases:remove:resources', function () {
    run("cd {{deploy_path}} && {{bin/sudo}} rm -rf skeleton release ");
});//->setPrivate();

after('releases:remove:all', 'releases:remove:resources');

desc("Remove all databases");
task('releases:remove:all:db', function () {
    $dbs = get('get_all_databases');
    foreach ($dbs as $db) {
        if (0 === strpos($db, get('database_name_prefix'))) {
            run("{{bin/mysql}} -Bse 'drop database $db'");
        }
    }
})->setPrivate();

after('releases:remove:all', 'releases:remove:all:db');

desc("Remove release (required --release=[RELEASE])");
task('releases:remove', function () {
    $release = input()->getOption('release');

    run("{{bin/sudo}} rm -rf {{deploy_path}}/releases/$release");

    $db = get('database_name_prefix') . $release;
    $dbs = get('get_all_databases');
    if (in_array($db, $dbs)) {
        run("{{bin/mysql}} -Bse 'drop database $db'");
    }

    run("cd {{deploy_path}} && if [ -e release ]; then rm release; fi");
    run("cd {{deploy_path}} && if [ -h release ]; then rm release; fi");
});
before('releases:remove', 'release:set');

set('database_name', function () {
    if (get('mysql_db') === false) {
        throw new RuntimeException("mysql_db is strong required option.\n");
    }
    return get('database_name_prefix') . (get('mysql_db') ? get('mysql_db') : get('release'));
});

set('admin_password', function () {
    return md5(get('deploy_path') . hash('sha256', get('servername')));
    // return get('database_name');
});

task('releases:db:list', function () {
    $dbs = get('get_all_databases');
    foreach ($dbs as $db) {
        if (0 === strpos($db, get('database_name_prefix'))) {
            writeln($db);
        }
    }
});
