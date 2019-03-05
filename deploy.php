<?php

namespace Deployer;

define('CUSTOM_RECIPE_DIR', realpath(__DIR__ . '/recipe/'));

require_once CUSTOM_RECIPE_DIR . '/common.php';
// require_once CUSTOM_RECIPE_DIR . '/magento1/skeleton.php';
// require_once CUSTOM_RECIPE_DIR . '/magento1/backup.php';
require_once CUSTOM_RECIPE_DIR . '/magento1.php';
// require_once CUSTOM_RECIPE_DIR . '/magento2/skeleton.php';
// require_once CUSTOM_RECIPE_DIR . '/magento2.php';
// require_once CUSTOM_RECIPE_DIR . '/magento2/backup.php';


set('keep_releases', 5);
set('default_stage', 'production');
set('use_relative_symlink', false);
set('use_atomic_symlink', false);
set('writable_use_sudo', true);

set('port', 22);
set('ssh_type', 'native');
set('forwardAgent', true);
set('multiplexing', true);

set('add_sample_data', false);
set('mysql_host', '127.0.0.1');
set('mysql_db', false);
set('packages', false);
set('magento_version', 2);

inventory('hosts.yml');
