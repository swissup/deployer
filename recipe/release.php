<?php

namespace Deployer;

set('release_path', function () {
    // return str_replace("\n", '', run("cd {{deploy_path}} && if [ -d release ]; then readlink release; fi"));
    return str_replace("\n", '', run("readlink {{deploy_path}}/release"));
});

desc('Show path to current release');
task('release:path', function () {
    writeln(get('release_path'));
});

set('release', function () {
    $path = str_replace("\n", '', run("readlink {{deploy_path}}/release"));
    return basename($path); //{{deploy_path}}/release
});

desc("Show current release (clone task current)");
task('release:current', function () {
    $release = get('release');
    writeln("$release");
});

// task('release:current:dir', function () {
//     $r = get('magento_root');
//     writeln("$r");
// })->desc("Show current magento root");

option(
    'release',
    null,
    \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL,
    'Release to set as current.'
);

desc("Change current release by option value");
task('release:set', function () {
    $release = input()->getOption('release');
    if (empty($release)) {
        writeln("<error>That task required option --release=[RELEASE]</error>");
    } else {
        $list = get('releases_list_all');
        if (in_array($release, $list)) {
            $releaseDir = get('deploy_path') . "/releases/$release";
            // run("cd {{deploy_path}} && {{bin/symlink}} $releaseDir current");
            set('release_path', $releaseDir);
        } else {
            writeln("<error>Wrong release option value $release</error>");
        }
    }
});
