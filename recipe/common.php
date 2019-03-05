<?php

namespace Deployer;

require 'recipe/common.php';

require_once CUSTOM_RECIPE_DIR . '/debug.php';
require_once CUSTOM_RECIPE_DIR . '/releases.php';

// set('httpuser', 'apache');
set('httpuser', function () {
    return run("ps aux | grep -E '[a]pache|[h]ttpd|[_]www|[w]ww-data|[n]ginx' "
        . "| grep -v root | head -1 | cut -d\  -f1");
});

set('owner', function () {
    // return getenv('USER');
    return run('whoami');
    return run("ls -ld {{release_path}} | awk '{print $3}'");
});

task('test', function () {
     // writeln(get('httpuser'));
     print_r(get('shared_dirs'));
     // print_r(get('option_packages'));
     // writeln(get('hostname'));
     // writeln(get('admin_password'));
     // writeln(get('database_name_prefix'));
     // writeln(get('database_name'));
     // writeln(getenv());

    // writeln(has('previous_release') ? 'true' : 'false');
});
