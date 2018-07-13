<?php

namespace Deployer;

require_once __DIR__ . '/bin/sudo.php';

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
        if (0 === strpos($db, "db")) {
            writeln("$db");
        }
    }
})->setPrivate();

/**
 * Cleanup
 */
desc('Cleaning up old releases first ' . get('keep_releases'));
task('releases:cleanup:old', function () {
    $releases = get('releases_list_all');
    $keep = get('keep_releases');
    while ($keep > 0) {
        array_shift($releases);
        --$keep;
    }
    $dbs = get('get_all_databases');
    foreach ($releases as $release) {
        run("{{bin/sudo}} rm -rf {{deploy_path}}/releases/$release");
        $db = "db$release";
        if (in_array($db, $dbs)) {
            run("{{bin/mysql}} -Bse 'drop database $db'");
        }
    }
});

desc("Remove old releases and databases [3 days]");
task('releases:cleanup:oldest', function () {
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
            $db = "db$release";
            if (in_array($db, $dbs)) {
                run("{{bin/mysql}} -Bse 'drop database $db'");
            }
        }
    }
    run("cd {{deploy_path}} && if [ -e release ]; then rm release; fi");
    run("cd {{deploy_path}} && if [ -h release ]; then rm release; fi");

    run("cd {{deploy_path}} && if [ -e skeleton ]; then rm skeleton; fi");
    run("cd {{deploy_path}} && if [ -h skeleton ]; then rm skeleton; fi");
});

desc("Remove all releases and databases");
task('releases:cleanup:all', function () {
    run("cd {{deploy_path}} && {{bin/sudo}} rm -rf releases/* current shared ");
});

desc("Remove skeleton and release dirs");
task('releases:cleanup:resources', function () {
    run("cd {{deploy_path}} && {{bin/sudo}} rm -rf skeleton release ");
});//->setPrivate();

after('releases:cleanup:all', 'releases:cleanup:resources');

desc("Remove all databases");
task('releases:cleanup:all:db', function () {
    $dbs = get('get_all_databases');
    foreach ($dbs as $db) {
        if (0 === strpos($db, "db")) {
            run("{{bin/mysql}} -Bse 'drop database $db'");
        }
    }
})->setPrivate();

after('releases:cleanup:all', 'releases:cleanup:all:db');
