<?php

namespace Deployer;

desc('Add .htaccess for redirect to /current');
task('magento2:deploy:apache:prepare', function () {

    if (get('hostname') === get('host')) {
        return;
    }
    $rewriteBase = basename(get('deploy_path'));
    if (!test('[ -f {{deploy_path}}/.htaccess ]')) {
        $htaccessContent = "<IfModule mod_rewrite.c>

############################################
## enable rewrites

    Options +FollowSymLinks
    RewriteEngine on

############################################
## rewrite everything else to */current
    RewriteRule ^$ current [L]
    # RewriteBase /{$rewriteBase}/
    # RewriteCond %{THE_REQUEST} /current/([^\s?]*) [NC]
    # RewriteRule ^ %1 [L,NE,R=302]
    # RewriteRule ^((?!current/).*)$ current [L,QSA]
    # RewriteRule ^((?!current/).*)$ current [L,NC]
</IfModule>";

        run("cd {{deploy_path}} && touch .htaccess");
        run("cd {{deploy_path}} && echo \"{$htaccessContent}\" > .htaccess");
    }
})
->setPrivate()
;
