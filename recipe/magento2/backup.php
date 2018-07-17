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
    writeln(run("cd {{release_path}} && {{bin/magerun2}} db:dump --strip=\"@stripped\" var/backups/{$timestamp}_db.sql"));
    writeln(run("cd {{release_path}} && echo {$timestamp} >> README.md"));
    writeln(run('cd {{release_path}} && {{bin/git}} add .'));
    writeln(run("cd {{release_path}} && {{bin/git}} commit -a -m \"Add code restore point: {$timestamp}\""));
    writeln(run("cd {{release_path}} && {{bin/git}} tag rollback.{$timestamp}"));
});
before('magento2:backup', 'release:set');

set('magento2_backup_list', function () {
    $backups = run("cd {{release_path}} && ls var/backups/*_db.sql | awk '{print $1}'");
    $backups = array_filter(explode("\n", $backups));

    $_backups = array();
    foreach ($backups as $path) {
        list($backup, ) = explode('_', basename($path));
        $_backups[] = $backup;
    }

    return $_backups;
});

desc('List backups');
task('magento2:backup:list', function () {
    // writeln(run('cd {{release_path}} && {{bin/magento}} info:backups:list'));
    // writeln(run("cd {{release_path}} && ls var/backups/*_db.sql | awk '{print $1}'"));
    $backups = get('magento2_backup_list');
    foreach ($backups as $backup) {
        writeln($backup . ' | ' . date('Y-m-d H:i:s', $backup));
    }
});
before('magento2:backup:list', 'release:set');

option(
    'rollback',
    null,
    \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL,
    'Set rollback point.'
);

desc('Roll back');
task('magento2:rollback', function () {

    $rollback = input()->getOption('rollback');
    if (empty($rollback)) {
        writeln("<info>magento2:backup:list - show all backup</info>");
        $rollback = false;
        $backups = get('magento2_backup_list');
        foreach ($backups as $backup) {
            if ($backup > $rollback) {
                $rollback = $backup;
            }
        }
    }

    if (empty($rollback)) {
        writeln("<error>That task required option --roolback=[BACKUP]</error>");
        return;
    }
    writeln($rollback);

    // writeln(run('cd {{release_path}} && {{bin/magerun2}} setup:rollback --db-file=' . $rollback . '_db.sql'));
    // writeln(run('cd {{release_path}} && {{bin/magerun2}} setup:rollback --code-file=' . $rollback . '_filesystem_code.tgz'));
    // writeln(run('cd {{release_path}} && {{bin/magerun2}} setup:rollback --media-file=' . $rollback . '_filesystem_media.tgz'));
    writeln(run("cd {{release_path}} && {{bin/magerun2}} db:import var/backups/{$rollback}_db.sql"));
    writeln(run("cd {{release_path}} && {{bin/git}} checkout rollback.{$rollback}"));
});
before('magento2:rollback', 'release:set');

desc('List backups');
task('magento2:rollback:list', ['magento2:backup:list']);
