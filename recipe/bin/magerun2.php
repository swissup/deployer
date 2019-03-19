<?php

namespace Deployer;

set('bin/magerun2', function () {
    if (!commandExist('n98-magerun2')) {
        run(
            "cd {{release_path}} "
            . "&& wget https://files.magerun.net/n98-magerun2.phar -O n98-magerun2 "
            . "&& chmod +x n98-magerun2"
            . "&& cp n98-magerun2 /usr/local/bin/n98-magerun2"
        );
    }

    if (commandExist('n98-magerun2')) {
        return locateBinaryPath('n98-magerun2');
    }

    return 'n98-magerun2';
});
