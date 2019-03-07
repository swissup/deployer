<?php

namespace Deployer;

require_once CUSTOM_RECIPE_DIR . '/magento1.php';
require_once CUSTOM_RECIPE_DIR . '/releases.php';

desc('Create backup snapshot');
task('magento:backup:create', function () {
    if (test("[ ! -d {{release_path}}/var/backups ]")) {
        run("mkdir -p {{release_path}}/var/backups");
    }
    $timestamp = time();//date('YmdHis');
    writeln(run("cd {{release_path}} && {{bin/magerun}} db:dump --quiet --strip=\"@stripped\" var/backups/{$timestamp}_db.sql"));
    // writeln(run("cd {{release_path}} && echo {$timestamp} >> README.md"));
    // writeln(run('cd {{release_path}} && {{bin/git}} add .'));
    // writeln(run("cd {{release_path}} && {{bin/git}} commit -a -m \"Add code restore point: {$timestamp}\""));
    // writeln(run("cd {{release_path}} && {{bin/git}} tag snapshot.{$timestamp}"));
});
// before('magento:backup:create', 'release:set');

set('magento_backup_list', function () {
    if (test("[ ! -d {{release_path}}/var/backups ]")) {
        run("mkdir -p {{release_path}}/var/backups");
    }
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
        writeln($backup . ' | ' . date('Y-m-d H:i:s', $backup));
    }
});
// before('magento:backup:list', 'release:set');

option(
    'backup',
    null,
    \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL,
    'Set backup point.'
);

desc('Rollback to backup snapshot (require --backup=[BACKUP_SNAPSHOT])');
task('magento:backup:rollback', function () {

    $backup = input()->getOption('backup');
    if (empty($backup)) {
        writeln("<info>magento:backup:list - list backups</info>");
        $backup = false;
        $backups = get('magento_backup_list');
        foreach ($backups as $_backup) {
            if ($_backup > $backup) {
                $backup = $_backup;
            }
        }
    }

    if (empty($backup)) {
        writeln("<error>That task required option --backup=[BACKUP_SNAPSHOT]</error>");
        return;
    }
    $backups = get('magento_backup_list');

    if (!in_array($backup, $backups)) {
        writeln("<error>That task required valid option --backup=[BACKUP_SNAPSHOT]</error>");
        writeln("<info>magento:backup:list - show all backups</info>");
        return;
    }

    writeln($backup);

    writeln(run("cd {{release_path}} && {{bin/magerun}} db:import --quiet var/backups/{$backup}_db.sql"));
    // writeln(run("cd {{release_path}} && {{bin/git}} checkout backup.{$backup}"));
});
// before('magento:rollback', 'release:set');
