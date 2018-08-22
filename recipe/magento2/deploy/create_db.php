<?php

namespace Deployer;

desc('Create database for magento 2 instance');
task('magento2:deploy:create_db', function () {
    run("{{bin/mysql}} -Bse 'DROP DATABASE IF EXISTS {{database_name}};'");
    run("{{bin/mysql}} -Bse 'CREATE DATABASE {{database_name}};'");
})->setPrivate();
