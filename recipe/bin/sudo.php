<?php

namespace Deployer;

set('bin/sudo', function () {
    return get('writable_use_sudo') ? 'sudo' : '';
});
