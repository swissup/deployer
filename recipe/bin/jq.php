<?php

namespace Deployer;

set('bin/jq', function () {
    if (!commandExist('jq')) {
        run("sudo yum install jq");
    }
    $jq = 'jq';
    $jqOptions = '';//'--indent 4';
    return "$jq $jqOptions";
});
