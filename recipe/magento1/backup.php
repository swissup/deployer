<?php

namespace Deployer;

require 'recipe/common.php';

require_once __DIR__ . '/../magento1.php';

desc('Backing up');
task('magento:backup', function () {
    if (test("[ ! -d {{release_path}}/var/backups ]")) {
        run("mkdir -p {{release_path}}/var/backups");
    }
    $timestamp = time();//date('YmdHis');
    writeln(run("cd {{release_path}} && {{bin/magerun}} db:dump --strip=\"@stripped\" var/backups/{$timestamp}_db.sql"));
    writeln(run("cd {{release_path}} && echo {$timestamp} >> README.md"));
    writeln(run('cd {{release_path}} && {{bin/git}} add .'));
    writeln(run("cd {{release_path}} && {{bin/git}} commit -a -m \"Add code restore point: {$timestamp}\""));
    writeln(run("cd {{release_path}} && {{bin/git}} tag rollback.{$timestamp}"));
});
before('magento:backup', 'release:set');

set('magento_backup_list', function () {
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
task('magento:backup:list', function () {
    $backups = get('magento_backup_list');
    foreach ($backups as $backup) {
        writeln($backup . '  ' . date('Y-m-d H:i:s', $backup));
    }
});
before('magento:backup:list', 'release:set');

option(
    'rollback',
    null,
    \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL,
    'Set rollback point.'
);

desc('Roll back');
task('magento:rollback', function () {

    $rollback = input()->getOption('rollback');
    if (empty($rollback)) {
        writeln("<info>magento:backup:list - show all backup</info>");
        $rollback = false;
        $backups = get('magento_backup_list');
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

    writeln(run("cd {{release_path}} && {{bin/magerun}} db:import var/backups/{$rollback}_db.sql"));
    writeln(run("cd {{release_path}} && {{bin/git}} checkout rollback.{$rollback}"));
});
before('magento:rollback', 'release:set');

desc('List backups');
task('magento:rollback:list', ['magento:backup:list']);
