<?php

namespace Deployer;

require 'recipe/common.php';

require_once __DIR__ . '/debug.php';
require_once __DIR__ . '/releases.php';

task('test', function () {
     writeln(get('httpuser'));
     writeln(get('owner'));
});
