<?php

namespace Deployer;

desc('Install magento 2 sampledata');
task('magento2:deploy:sampledata:install', function () {

    if (!get('add_sample_data')) {
        return;
    }
    // run("cd {{release_path}} && {{bin/magento}} sampledata:deploy", [
    //     'timeout' => 1000
    // ]);
    // run("cd {{release_path}} && {{bin/magento}} setup:upgrade", [
    //     'timeout' => 600
    // ]);
    // return;

    run(
        "if [ ! -d {{deploy_path}}/magento2-sample-data ]; then  "
        . "cd {{deploy_path}};"
        . "{{bin/git}} clone git@github.com:magento/magento2-sample-data.git;"
        . "{{bin/sudo}} chown -R :{{httpuser}} magento2-sample-data ;"
        . "{{bin/sudo}} find magento2-sample-data -type d -exec chmod g+ws {} \; ;"
        . " fi"
    );
    run("cd {{deploy_path}}/magento2-sample-data && {{bin/git}} fetch && {{bin/git}} checkout ");

    if (input()->hasOption('tag')) {
        $tag = input()->getOption('tag');
    }
    if (empty($tag)) {
        $tag = get('magento2_repository_last_tag');
    }

    if (!empty($tag)) {
        run("cd {{deploy_path}}/magento2-sample-data && {{bin/git}} checkout $tag");
    }
    run(
        "php -f {{deploy_path}}/magento2-sample-data/dev/tools/build-sample-data.php -- "
        . "--ce-source=\"{{release_path}}\""
    );
    if (!empty($tag)) {
        run("cd {{deploy_path}}/magento2-sample-data && {{bin/git}} checkout ");
    }

    //@todo split after task
    // run("cd {{release_path}} && {{bin/sudo}} rm -rf var/cache/* var/page_cache/* var/generation/*");
    run("cd {{release_path}} && {{bin/magento}} setup:upgrade", [
        'timeout' => 1200
    ]);
})->setPrivate();
