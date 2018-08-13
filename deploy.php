<?php

namespace Deployer;

require_once __DIR__ . '/recipe/common.php';
// require_once __DIR__ . '/recipe/skeleton/magento1.php';
// require_once __DIR__ . '/recipe/skeleton/magento2.php';
require_once __DIR__ . '/recipe/magento1/backup.php';
require_once __DIR__ . '/recipe/magento2/backup.php';

set('keep_releases', 5);
set('shared_dirs', []);
set('shared_files', []);
set('use_relative_symlink', false);
set('use_atomic_symlink', false);
set('ssh_type', 'native');
set('default_stage', 'production');
set('mysql_db', false);
set('writable_use_sudo', true);
set('add_sample_data', false);

inventory('hosts.yml');
