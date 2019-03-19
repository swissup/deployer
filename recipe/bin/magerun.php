<?php

namespace Deployer;

set('bin/magerun', function () {
    if (!commandExist('n98-magerun')) {
        run(
            "cd {{release_path}} "
            . "&& wget http://files.magerun.net/n98-magerun-latest.phar -O n98-magerun "
            . "&& chmod +x n98-magerun"
            . "&& cp n98-magerun /usr/local/bin/n98-magerun"
        );
    }

    if (commandExist('n98-magerun')) {
        return locateBinaryPath('n98-magerun');
    }
    return 'n98-magerun';
});
