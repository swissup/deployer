<?php
namespace Deployer;

require_once CUSTOM_RECIPE_DIR . '/options/packages.php';
require_once CUSTOM_RECIPE_DIR . '/options/modules.php';

desc('Prepare magento release place');
task('magento:deploy:release', function () {
    if (input()->hasOption('release') && !empty(input()->getOption('release'))) {
        $release = input()->getOption('release');
    } else {
        $tag = get('tag');
        if (input()->hasOption('tag') && !empty(input()->getOption('tag'))) {
            $tag = input()->getOption('tag');
        }
        if (empty($tag)) {
            $tag = get('magento_repository_last_tag');
        }
        $tag = str_replace('.', '', $tag);
        $tag = str_pad($tag, 4, '0');

        $release = $tag . date('YmdHis');
    }
    $release = preg_replace("/[^A-Za-z0-9 ]/", '', $release);

    $releasePath = "{{deploy_path}}/releases/$release";

    run("mkdir $releasePath");
    run("cd {{deploy_path}} && if [ -h release ]; then rm release; fi");
    run("cd {{deploy_path}} && {{bin/symlink}} $releasePath release");
})->setPrivate();


desc('Add .htaccess for redirect to /current');
task('magento:deploy:apache:htaccess', function () {

    $rewriteBase = basename(get('deploy_path'));
    if (!test('[ -f {{deploy_path}}/.htaccess ]')) {
        $htaccessContent = "<IfModule mod_rewrite.c>

############################################
## enable rewrites

    Options +FollowSymLinks
    RewriteEngine on

############################################
## rewrite everything else to current subdir
    RewriteRule ^$ current [L]
</IfModule>";

        run("cd {{deploy_path}} && touch .htaccess");
        run("cd {{deploy_path}} && echo \"{$htaccessContent}\" > .htaccess");
    }
})
->setPrivate()
;

/**
 * Update project code
 */
desc('Updating code (git clone)');
task('magento:deploy:update_code', function () {
    $repository = get('repository');
    $gitCache = get('git_cache');
    $depth = $gitCache ? '' : '--depth 1';

    if (input()->hasOption('branch')
        && !empty(input()->getOption('branch'))
    ) {
        $branch = input()->getOption('branch');
    }
    $tag = get('tag');
    if (input()->hasOption('tag')
        && !empty(input()->getOption('tag'))
    ) {
        $tag = input()->getOption('tag');
    }
    $at = '';
    if (!empty($tag)) {
        $at = "-b $tag";
    } elseif (!empty($branch)) {
        $at = "-b $branch";
    } else {
        $tag = get('magento_repository_last_tag');
        $at = "-b $tag";
    }

    $releases = get('magento_releases_list');
    if ($gitCache && isset($releases[1])) {
        try {
            run(
                "{{bin/git}} clone $at --recursive -q --reference {{deploy_path}}/releases/{$releases[1]} --dissociate "
                . "$repository  {{release_path}} 2>&1"
            );
        } catch (RuntimeException $exc) {
            run("{{bin/git}} clone $at --recursive -q $repository {{release_path}} 2>&1");
        }
    } else {
        //run("{{bin/git}} clone $at $depth --recursive -q $repository {{release_path}} 2>&1");
        run("{{bin/git}} clone -q $repository {{release_path}} 2>&1");
    }
    if (!empty($tag)) {
        run("cd {{release_path}} && {{bin/git}} checkout $tag");
    }
    run("cd {{release_path}} && {{bin/git}} config core.fileMode false");
})->setPrivate();


desc('Creating symlink to release');
task('magento:deploy:symlink', function () {
    // run("cd {{deploy_path}} && {{bin/symlink}} {{release_path}} current"); // Atomic override symlink.
    run("cd {{deploy_path}} && if [ -h release ]; then rm release; fi");
    // run("cd {{deploy_path}} && rm release"); // Remove release link.
})->setPrivate();
