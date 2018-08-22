<?php

namespace Deployer;

task('magento2:deploy:check', function () {
    date_default_timezone_set('America/New_York');
    if (test("[ -h {{deploy_path}}/release ]")) {
        $release = run("readlink {{deploy_path}}/release");
        $release = explode('/', $release);
        $release = end($release);
        $release = substr($release, 4);
        $release = strtotime($release);

        if (14400 > abs(time() - $release)) {
            writeln("<error>Resource 'release' is busy</error>");
            throw new RuntimeException("Resource 'release' is busy\n");
            die;
        } else {
            writeln("<error>Resource 'release' is old and has been removed</error>");
            run("rm -rf {{deploy_path}}/release ");
        }
    }
})->setPrivate();
