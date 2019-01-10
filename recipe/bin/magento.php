<?php

namespace Deployer;

set('bin/magento', function () {
    return '{{bin/php}} {{php_cli_params}} bin/magento';
});
