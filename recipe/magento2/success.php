<?php

namespace Deployer;

desc('Show after installation success info');
task('magento2:success', function () {
    $releasePath = get('release_path');
    $release = basename($releasePath);
    $baseUrl = get('base_url');
    $databaseName = get('database_name');
    $password = get('admin_password');

    writeln("Dir : <comment>$releasePath</comment>");
    writeln("Db  : <comment>$databaseName</comment>");
    writeln("Url : <comment>$baseUrl</comment>");
    writeln("Admin Url : <comment>$baseUrl/index.php/admin admin $password</comment>");
})->setPrivate();

task('magento2:credentials', function () {
    $releasePath = get('release_path');
    $release = basename($releasePath);
    $baseUrl = get('base_url');
    $databaseName = get('database_name');
    $password = get('admin_password');

    writeln("Dir : <comment>$releasePath</comment>");
    writeln("Db  : <comment>$databaseName</comment>");
    writeln("Frontend : <comment>$baseUrl</comment>");
    writeln("Backend : <comment>$baseUrl/index.php/admin admin $password</comment>");
});
