<?php

namespace Deployer;

require_once CUSTOM_RECIPE_DIR . '/common.php';
require_once CUSTOM_RECIPE_DIR . '/releases.php';

set('magento2_repository', 'git@github.com:magento/magento2.git');

set('magento2_releases_list', function () {
    run("if [ ! -d {{deploy_path}}/releases ]; then mkdir -p {{deploy_path}}/releases; fi");
    $list = run("ls {{deploy_path}}/releases");
    $list = explode("\n", $list);
    rsort($list);

    $repo = get('magento2_repository');
    $_list = array();
    foreach ($list as $release) {
        $releaseDir = "{{deploy_path}}/releases/{$release}";
        $remote = run("cd {$releaseDir} && if [ -d {$releaseDir}/.git ]; then {{bin/git}} remote -v; fi");
        if (false !== strpos($remote, $repo)) {
            $_list[] = $release;
        }
    }
    return $_list;
});

set('magento2_repository_last_tag', function () {
    $repository = get('magento2_repository');
    // $branch = get('branch');
    // if (empty($branch)) {
    //     $branch = 'master';
    // }
    $branch = '';
    $tags = run("cd {{deploy_path}} && {{bin/git}} ls-remote --tags $repository $branch");
    $tags = explode("\n", $tags);
    // print_r($tags);
    $tag = end($tags);
    list(, $tag) = explode('refs/tags/', $tag);
    list($tag, ) = explode('-', $tag);

    return  $tag;
});

desc('List all magento 2 releases');
task('magento2:releases:list', function () {
    $releases = get('magento2_releases_list');
    if (!empty($releases)) {
        $releases = implode("\n", $releases);
        writeln("$releases");
    }
});

before('magento2:releases:list', 'deploy:info');

task('magento2:repository:last:tag', function () {
    $tag = get('magento2_repository_last_tag');
    writeln("$tag");
})->setPrivate();
