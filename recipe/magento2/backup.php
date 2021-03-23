<?php

namespace Deployer;

require_once CUSTOM_RECIPE_DIR . '/magento2.php';

set('backups_path', "{{release_path}}/var/backups");

desc('Backing up');
task('magento2:backup:create', function () {
    if (test("[ ! -d {{backups_path}} ]")) {
        run("mkdir -p {{backups_path}}");
    }
    // writeln(run('cd {{release_path}} && {{bin/magerun2}} setup:backup --code'));
    // writeln(run('cd {{release_path}} && {{bin/magerun2}} setup:backup --db '));
    // writeln(run('cd {{release_path}} && {{bin/magerun2}} setup:backup --media'));
    $timestamp = time();//date('YmdHis');
    // writeln(run("cd {{release_path}} && {{bin/magerun2}} db:dump --quiet --strip=\"@stripped\" {{backups_path}}/{$timestamp}_db.sql"));
    writeln(run("cd {{current_path}} && {{bin/magerun2}} db:dump --quiet {{backups_path}}/{$timestamp}_db.sql"));
    // writeln(run("cd {{release_path}} && echo {$timestamp} >> README.md"));
    // writeln(run('cd {{release_path}} && {{bin/git}} add .'));
    // writeln(run("cd {{release_path}} && {{bin/git}} commit -a -m \"Add code restore point: {$timestamp}\""));
    // writeln(run("cd {{release_path}} && {{bin/git}} tag snapshot.{$timestamp}"));
});

set('magento2_backup_list', function () {
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
task('magento2:backup:list', function () {
    // writeln(run('cd {{release_path}} && {{bin/magento}} info:backups:list'));
    // writeln(run("cd {{release_path}} && ls {{backups_path}}/*_db.sql | awk '{print $1}'"));
    $backups = get('magento2_backup_list');
    if (count($backups) == 0) {
        writeln('<info>Nothing found</info>');
    } else {
        foreach ($backups as $backup) {
            writeln($backup . ' | ' . date('Y-m-d H:i:s', $backup));
        }
    }
});

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
        $backups = get('magento2_backup_list');
        foreach ($backups as $_backup) {
            if ($_backup > $backup) {
                $backup = $_backup;
            }
        }
    }

    return $backup;
});

desc('Roll back');
task('magento2:backup:rollback', function () {

    $backup = get('backup');

    if (empty($backup)) {
        writeln("<error>That task required option --backup=[SNAPSHOT]</error>");
        return;
    }
    $backups = get('magento2_backup_list');

    if (!in_array($backup, $backups)) {
        writeln("<error>That task required valid option --backup=[SNAPSHOT]</error>");
        writeln("<info>magento2:backup:list - list all backup</info>");
        return;
    }
    writeln($backup);

    // writeln(run('cd {{release_path}} && {{bin/magerun2}} setup:rollback --db-file=' . $backup . '_db.sql'));
    // writeln(run('cd {{release_path}} && {{bin/magerun2}} setup:rollback --code-file=' . $backup . '_filesystem_code.tgz'));
    // writeln(run('cd {{release_path}} && {{bin/magerun2}} setup:rollback --media-file=' . $backup . '_filesystem_media.tgz'));
    writeln(run("{{bin/magerun2}} db:import --root-dir={{deploy_path}}/current --quiet {{backups_path}}/{$backup}_db.sql"));
    // writeln(run("cd {{release_path}} && {{bin/git}} checkout snapshot.{$backup}"));
});

desc('Add backup cronjob');
task('magento2:backup:rollback:cronjob', function () {
    $cronJobKey = hash('crc32', get('servername')) . '_CRON_JOBS_FROM_DEPLOYMENT';

    //delete all cronjobs with unique key
    $resetCronJobsFromDeployment = sprintf('crontab -l | grep -v "%s" | crontab -', $cronJobKey);
    writeln('Resetting crontab list using key: ' . $cronJobKey . ' (' . get('servername') . ')');
    run($resetCronJobsFromDeployment);

    //add cronjob with unique key
    $backup = get('backup');
    if (empty($backup)) {
        return;
    }
    $cronjob = parse("{{bin/magerun2}} db:import --quiet --root-dir={{deploy_path}}/current {{backups_path}}/{$backup}_db.sql");
    $time = '0 */12 * * * ';
    // $time = '*/10 * * * * ';
    $cronjob = $time . $cronjob;
    $cronjob = sprintf('%s #%s', $cronjob, $cronJobKey);
    writeln('Adding cron');
    writeln($cronjob);

    run('(crontab -l ; echo "' . $cronjob . '") | crontab -');
});
