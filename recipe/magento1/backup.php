<?php

namespace Deployer;

require 'recipe/common.php';

require_once __DIR__ . '/../magento1.php';

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

task('magento:backup:list', function () {
    $backups = run("cd {{release_path}} && ls var/backups/*_db.sql | awk '{print $1}'");
    foreach (explode("\n", $backups) as $path) {
        list($backup, ) = explode('_', basename($path));
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
task('magento:rollback', function () {

    $rollback = input()->getOption('rollback');
    if (empty($rollback)) {
        writeln("<error>That task required option --roolback=[BACKUP]</error>");
        writeln("<info>magento:backup:list - show all backup</info>");
        return;
    }
    writeln(run("cd {{release_path}} && {{bin/magerun}} db:import var/backups/{$rollback}_db.sql"));
    writeln(run("cd {{release_path}} && {{bin/git}} checkout rollback.{$rollback}"));
});
before('magento:rollback', 'release:set');

task('magento:rollback:list', ['magento:backup:list']);
