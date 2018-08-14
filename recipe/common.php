<?php

namespace Deployer;

require 'recipe/common.php';

require_once __DIR__ . '/debug.php';
require_once __DIR__ . '/releases.php';

task('test', function () {
     // writeln(get('httpuser'));
     // writeln(get('current_path'));
     writeln(get('admin_password'));
     writeln(get('admin_password'));
    // writeln(has('previous_release') ? 'true' : 'false');
});
