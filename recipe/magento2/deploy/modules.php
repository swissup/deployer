<?php

namespace Deployer;

require_once CUSTOM_RECIPE_DIR . '/bin/magento.php';

task('magento2:deploy:modules:enable', function () {
    $modules = get('option_modules');
    // print_r($modules);
    if (empty($modules)) {
        $modules = '--all';
    } else {
        $modules = implode(' ', $modules);
    }
    // $status = run("cd {{release_path}} && {{bin/magento}} module:status");
    // $rm = 'None';
    // $status = str_replace($rm, '', $status);
    // $delimiter ='List of disabled modules:';
    // list($enable, $disable) = explode($delimiter, $status);
    // $rm = 'List of enabled modules:';
    // // $enable = str_replace($rm, '', $enable);
    // $modules = explode("\n", $disable);
    // $modules = array_filter($modules);

    $options = '';//' --clear-static-content --force ';
    run(
        "cd {{release_path}} "
        . "&& {{bin/magento}} module:enable $options $modules"
        // . "&& {{bin/magento}} setup:upgrade"
    );
})->setPrivate();
