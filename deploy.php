<?php

namespace Deployer;

require_once __DIR__ . '/recipe/common.php';
// require_once __DIR__ . '/recipe/skeleton/magento1.php';
// require_once __DIR__ . '/recipe/skeleton/magento2.php';
// require_once __DIR__ . '/recipe/magento1/backup.php';
// require_once __DIR__ . '/recipe/magento2/backup.php';
require_once __DIR__ . '/recipe/magento2.php';

set('keep_releases', 5);
set('use_relative_symlink', false);
set('use_atomic_symlink', false);
set('port', 22);
set('ssh_type', 'native');
set('forwardAgent', true);
set('multiplexing', true);
set('default_stage', 'production');
set('mysql_db', false);
set('writable_use_sudo', true);
set('add_sample_data', false);
set('mysql_host', '127.0.0.1');

// set('packages', false);

inventory('hosts.yml');
