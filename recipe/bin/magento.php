<?php

namespace Deployer;

set('bin/magento', function () {
    return '{{bin/php}} bin/magento';
});
