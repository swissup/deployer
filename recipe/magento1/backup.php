<?php

namespace Deployer;

require_once CUSTOM_RECIPE_DIR . '/magento1.php';
require_once CUSTOM_RECIPE_DIR . '/releases.php';

set('backups_path', "{{release_path}}/var/backups");

desc('Create backup snapshot');
task('magento:backup:create', function () {
    if (test("[ ! -d {{backups_path}} ]")) {
        run("mkdir -p {{backups_path}}");
    }
    $timestamp = time();//date('YmdHis');
    // writeln(run("cd {{release_path}} && {{bin/magerun}} db:dump --quiet --strip=\"@stripped\" {{backups_path}}/{$timestamp}_db.sql"));
    writeln(run("cd {{release_path}} && {{bin/magerun}} db:dump --quiet {{backups_path}}/{$timestamp}_db.sql"));
    // writeln(run("cd {{release_path}} && echo {$timestamp} >> README.md"));
    // writeln(run('cd {{release_path}} && {{bin/git}} add .'));
    // writeln(run("cd {{release_path}} && {{bin/git}} commit -a -m \"Add code restore point: {$timestamp}\""));
    // writeln(run("cd {{release_path}} && {{bin/git}} tag snapshot.{$timestamp}"));
});
// before('magento:backup:create', 'release:set');

set('magento_backup_list', function () {
    if (test("[ ! -d {{backups_path}} ]")) {
        run("mkdir -p {{backups_path}}");
    }
    $backups = run("cd {{release_path}} && ls {{backups_path}}/*_db.sql | awk '{print $1}'");
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

set('backup', function () {

    $backup = input()->getOption('backup');
    if (empty($backup)) {
        // writeln("<info>magento:backup:list - list backups</info>");
        $backup = false;
        $backups = get('magento_backup_list');
        foreach ($backups as $_backup) {
            if ($_backup > $backup) {
                $backup = $_backup;
            }
        }
    }

    return $backup;
});

desc('Rollback to backup snapshot (require --backup=[BACKUP_SNAPSHOT])');
task('magento:backup:rollback', function () {

    $backup = get('backup');

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

    writeln(run("{{bin/magerun}} db:import --root-dir={{deploy_path}}/current {{backups_path}}/{$backup}_db.sql"));
    // writeln(run("cd {{release_path}} && {{bin/git}} checkout backup.{$backup}"));
});
// before('magento:rollback', 'release:set');

desc('Add backup cronjob');
task('magento:backup:rollback:cronjob', function () {
    $cronJobKey = hash('crc32', get('hostname')) . '_CRON_JOBS_FROM_DEPLOYMENT';

    //delete all cronjobs with unique key
    $resetCronJobsFromDeployment = sprintf('crontab -l | grep -v "%s" | crontab -', $cronJobKey);
    writeln('Resetting crontab list using key: ' . $cronJobKey . ' (' . get('hostname') . ')');
    run($resetCronJobsFromDeployment);

    //add cronjob with unique key
    $backup = get('backup');
    if (empty($backup)) {
        return;
    }
    $cronjob = parse("{{bin/magerun}} db:import --quiet --root-dir={{deploy_path}}/current {{backups_path}}/{$backup}_db.sql");
    $time = '0 */12 * * * ';
    // $time = '*/10 * * * * ';
    $cronjob = $time . $cronjob;
    $cronjob = sprintf('%s #%s', $cronjob, $cronJobKey);
    writeln('Adding cron');
    writeln($cronjob);

    run('(crontab -l ; echo "' . $cronjob . '") | crontab -');
});
