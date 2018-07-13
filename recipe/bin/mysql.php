<?php

namespace Deployer;

set('bin/mysql', function () {
    $user = get('mysql_user');
    $pass = get('mysql_pass');
    return "mysql -u$user -p$pass";
});
//https://stackoverflow.com/questions/20751352/suppress-warning-messages-using-mysql-from-within-terminal-but-password-written
// set('mysql', 'mysql --login-path=local');
