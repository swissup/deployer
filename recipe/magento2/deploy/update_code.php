<?php

namespace Deployer;

/**
 * Update project code
 */
desc('Updating code magento 2 code (git clone)');
task('magento2:deploy:update_code', function () {
    $repository = get('repository');
    $gitCache = get('git_cache');
    $depth = $gitCache ? '' : '--depth 1';
    $branch = get('branch');
    if (input()->hasOption('branch')) {
        $branch = input()->getOption('branch');
    }
    if (input()->hasOption('tag')) {
        $tag = input()->getOption('tag');
    }
    $releases = get('magento2_releases_list');
    $at = '';
    if (!empty($tag)) {
        $at = "-b $tag";
    } elseif (!empty($branch)) {
        $at = "-b $branch";
    } elseif (has('previous_release') || ($gitCache && isset($releases[1]))) {
        $tag = run("cd {{deploy_path}}/releases/{$releases[1]} && {{bin/git}} tag -l --points-at HEAD");
        $at = "-b $tag";
    } else {
        $tag = get('magento2_repository_last_tag');
        $at = "-b $tag";
    }

    if ($gitCache && isset($releases[1])) {
        try {
            run(
                "{{bin/git}} clone $at --recursive -q --reference {{deploy_path}}/releases/{$releases[1]} --dissociate "
                . "$repository  {{release_path}} 2>&1",
                ['timeout' => 600]
            );
        } catch (RuntimeException $exc) {
            run("{{bin/git}} clone $at --recursive -q $repository {{release_path}} 2>&1", [
                'timeout' => 600
            ]);
        }
    } else {
        run("{{bin/git}} clone $at $depth --recursive -q $repository {{release_path}} 2>&1", [
            'timeout' => 600
        ]);
    }
    if (!empty($tag)) {
        run("cd {{release_path}} && {{bin/git}} checkout $tag");
    }
    run("cd {{release_path}} && {{bin/git}} config core.fileMode false");
})->setPrivate();
