<?php

namespace Deployer;

desc('Prepare magento 2 release place');
task('magento2:deploy:release', function () {
    if (input()->hasOption('release') && !empty(input()->getOption('release'))) {
        $release = input()->getOption('release');
    } else {
        if (input()->hasOption('tag')) {
            $tag = input()->getOption('tag');
            if (!empty($tag)) {
                $tag = str_replace('.', '', $tag);
                $tag = str_pad($tag, 4, '0');
            }
        }
        if (empty($tag)) {
            $tag = get('magento2_repository_last_tag');
        }
        $tag = str_replace('.', '', $tag);
        $tag = str_pad($tag, 4, '0');

        $release = $tag . date('YmdHis');
    }
    $release = preg_replace("/[^A-Za-z0-9 ]/", '', $release);

    $releasePath = "{{deploy_path}}/releases/$release";
    $i = 0;
    // while (is_dir(env()->parse($releasePath)) && $i < 42) {
    while (is_dir($releasePath) && $i < 42) {
        $releasePath .= '.' . ++$i;
    }
    run("mkdir $releasePath");
    run("cd {{deploy_path}} && if [ -h release ]; then rm release; fi");
    run("cd {{deploy_path}} && {{bin/symlink}} $releasePath release");
    set('release_path', $releasePath);

    $releasesList = get('magento2_releases_list');
    if (isset($releasesList[1])) {
        set('previous_release', "{{deploy_path}}/releases/{$releasesList[1]}");
    }
})->setPrivate();
