<?php

namespace Deployer;

require_once CUSTOM_RECIPE_DIR . '/magento1.php';
require_once CUSTOM_RECIPE_DIR . '/releases.php';

desc('Backing up');
task('magento:backup', function () {
    if (test("[ ! -d {{release_path}}/var/backups ]")) {
        run("mkdir -p {{release_path}}/var/backups");
    }
    $timestamp = time();//date('YmdHis');
    writeln(run("cd {{release_path}} && {{bin/magerun}} db:dump --quiet --strip=\"@stripped\" var/backups/{$timestamp}_db.sql"));
    writeln(run("cd {{release_path}} && echo {$timestamp} >> README.md"));
    writeln(run('cd {{release_path}} && {{bin/git}} add .'));
    writeln(run("cd {{release_path}} && {{bin/git}} commit -a -m \"Add code restore point: {$timestamp}\""));
    writeln(run("cd {{release_path}} && {{bin/git}} tag snapshot.{$timestamp}"));
});
before('magento:backup', 'release:set');

set('magento_snapshot_list', function () {
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

desc('List backup snapshots');
task('magento:snapshot:list', function () {
    $snapshots = get('magento_snapshot_list');
    foreach ($snapshots as $snapshot) {
        writeln($snapshot . ' | ' . date('Y-m-d H:i:s', $snapshot));
    }
});
before('magento:snapshot:list', 'release:set');

option(
    'snapshot',
    null,
    \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL,
    'Set snapshot point.'
);

desc('Roll back (require --snapshot=[SNAPSHOT])');
task('magento:rollback', function () {

    $snapshot = input()->getOption('snapshot');
    if (empty($snapshot)) {
        writeln("<info>magento:snapshot:list - show all backup snapshots</info>");
        $snapshot = false;
        $snapshots = get('magento_snapshot_list');
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
    $snapshots = get('magento_snapshot_list');

    if (!in_array($snapshot, $snapshots)) {
        writeln("<error>That task required valid option --snapshot=[SNAPSHOT]</error>");
        writeln("<info>magento:snapshot:list - show all backup</info>");
        return;
    }

    writeln($snapshot);

    writeln(run("cd {{release_path}} && {{bin/magerun}} db:import --quiet var/backups/{$snapshot}_db.sql"));
    writeln(run("cd {{release_path}} && {{bin/git}} checkout snapshot.{$snapshot}"));
});
before('magento:rollback', 'release:set');
