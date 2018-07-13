<?php

namespace Deployer;

task('debug:df', function () {
    writeln(run('df'));
});

task('debug:ps', function () {
    // writeln(run("ps auxwww | grep /usr/bin/php  | grep -v grep | grep -v deploy.sh")->toString());
    writeln(run('ps auxwww'));
});
