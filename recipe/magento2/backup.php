<?php

namespace Deployer;

require 'recipe/common.php';

require_once __DIR__ . '/../magento2.php';
require_once __DIR__ . '/../releases.php';

desc('Backing up');
task('magento2:backup', function () {
    if (test("[ ! -d {{release_path}}/var/backups ]")) {
        run("mkdir -p {{release_path}}/var/backups");
    }
    // writeln(run('cd {{release_path}} && {{bin/magerun2}} setup:backup --code'));
    // writeln(run('cd {{release_path}} && {{bin/magerun2}} setup:backup --db '));
    // writeln(run('cd {{release_path}} && {{bin/magerun2}} setup:backup --media'));
    $timestamp = time();//date('YmdHis');
    writeln(run("cd {{release_path}} && {{bin/magerun2}} db:dump --quiet --strip=\"@stripped\" var/backups/{$timestamp}_db.sql"));
    writeln(run("cd {{release_path}} && echo {$timestamp} >> README.md"));
    writeln(run('cd {{release_path}} && {{bin/git}} add .'));
    writeln(run("cd {{release_path}} && {{bin/git}} commit -a -m \"Add code restore point: {$timestamp}\""));
    writeln(run("cd {{release_path}} && {{bin/git}} tag snapshot.{$timestamp}"));
});
before('magento2:backup', 'release:set');

set('magento2_snapshot_list', function () {
    if (test("[ ! -d {{release_path}}/var/backups ]")) {
        run("mkdir -p {{release_path}}/var/backups");
    }
    $snapshots = run("cd {{release_path}} && ls var/backups/*_db.sql | awk '{print $1}'");
    $snapshots = array_filter(explode("\n", $snapshots));

    $_backups = array();
    foreach ($snapshots as $path) {
        list($snapshot, ) = explode('_', basename($path));
        $_backups[] = $snapshot;
    }

    return $_backups;
});

desc('List backups');
task('magento2:snapshot:list', function () {
    // writeln(run('cd {{release_path}} && {{bin/magento}} info:backups:list'));
    // writeln(run("cd {{release_path}} && ls var/backups/*_db.sql | awk '{print $1}'"));
    $snapshots = get('magento2_snapshot_list');
    foreach ($snapshots as $snapshot) {
        writeln($snapshot . ' | ' . date('Y-m-d H:i:s', $snapshot));
    }
});
before('magento2:snapshot:list', 'release:set');

option(
    'snapshot',
    null,
    \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL,
    'Set snapshot point.'
);

desc('Roll back');
task('magento2:rollback', function () {

    $snapshot = input()->getOption('snapshot');
    if (empty($snapshot)) {
        writeln("<info>magento2:snapshot:list - show all backup</info>");
        $snapshot = false;
        $snapshots = get('magento2_snapshot_list');
        foreach ($snapshots as $_snapshot) {
            if ($_snapshot > $snapshot) {
                $snapshot = $_snapshot;
            }
        }
    }

    if (empty($snapshot)) {
        writeln("<error>That task required option --snapshot=[SNAPSHOT]</error>");
        return;
    }
    $snapshots = get('magento2_snapshot_list');

    if (!in_array($snapshot, $snapshots)) {
        writeln("<error>That task required valid option --snapshot=[SNAPSHOT]</error>");
        writeln("<info>magento2:snapshot:list - show all backup</info>");
        return;
    }
    writeln($snapshot);

    // writeln(run('cd {{release_path}} && {{bin/magerun2}} setup:rollback --db-file=' . $snapshot . '_db.sql'));
    // writeln(run('cd {{release_path}} && {{bin/magerun2}} setup:rollback --code-file=' . $snapshot . '_filesystem_code.tgz'));
    // writeln(run('cd {{release_path}} && {{bin/magerun2}} setup:rollback --media-file=' . $snapshot . '_filesystem_media.tgz'));
    writeln(run("cd {{release_path}} && {{bin/magerun2}} db:import --quiet var/backups/{$snapshot}_db.sql"));
    writeln(run("cd {{release_path}} && {{bin/git}} checkout snapshot.{$snapshot}"));
});
before('magento2:rollback', 'release:set');
