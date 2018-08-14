<?php

namespace Deployer;

option(
    'packages',
    null,
    \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL,
    'Packages to deploy. --packages=swissup/core:*,swissup/askit:*'
);

set('option_packages', function () {
    $packages = [];
    if (input()->hasOption('packages')) {
        $rawpackages = input()->getOption('packages');

        if (empty($rawpackages)) {
            $packages = get('packages');
        } else {
            $packages = $rawpackages;
        }
        $packages = explode(',', $packages);

        $requireall = function ($vendor) {
            $packages = [];
            $url = "https://$vendor.github.io/packages/";
            $includes = json_decode(@file_get_contents($url . 'packages.json'), true);
            $include  = current(array_keys($includes['includes']));
            $packages = json_decode(@file_get_contents($url . $include), true);
            $packages = $packages['packages'];

            // remove requires
            // foreach ($packages as $p) {
            //     $p = isset($p['dev-master']) ? $p['dev-master'] : current($p);
            //     $requires = isset($p['require']) ? array_keys($p['require']) : [];
            //     foreach ($requires as $require) {
            //         if (isset($packages[$require])) {
            //             // writeln($require . '  ' . $p['name']);
            //             unset($packages[$require]);
            //         }
            //     }
            // }

            return array_keys($packages);
        };

        if (in_array('tm/*', $packages)) {
            $packages = array_merge($packages, $requireall('tmhub'));
        } elseif (in_array('swissup/*', $packages)) {
            $packages = array_merge($packages, $requireall('swissup'));
        }
        $packages = array_diff($packages, ['tm/*', 'swissup/*']);

        foreach ($packages as &$package) {
            if (false === strpos($package, ':')) {
                $package .= ':*';
            }
        }
        $packages = array_unique($packages);
    }
    return array_filter($packages);
});

set('option_packages_filtred', function () {
    $packages = [];
    if (input()->hasOption('packages')) {
        $rawpackages = input()->getOption('packages');

        if (empty($rawpackages)) {
            $packages = get('packages');
        } else {
            $packages = $rawpackages;
        }
        $packages = explode(',', $packages);

        $requireall = function ($vendor) {
            $packages = [];
            $url = "https://$vendor.github.io/packages/";
            $includes = json_decode(@file_get_contents($url . 'packages.json'), true);
            $include  = current(array_keys($includes['includes']));
            $packages = json_decode(@file_get_contents($url . $include), true);
            $packages = $packages['packages'];

            // filtering not magento[1,2]-module
            $filters = array();
            foreach ($packages as $p) {
                $p = isset($p['dev-master']) ? $p['dev-master'] : current($p);
                if (!isset($p['type'])
                    || ($p['type'] != 'magento2-module') && ($p['type'] != 'magento-module')) {
                    $filters[] = $p['name'];
                }
            }
            foreach ($filters as $filter) {
                if (isset($packages[$filter])) {
                    unset($packages[$filter]);
                }
            }
            // remove requires
            // foreach ($packages as $p) {
            //     $p = isset($p['dev-master']) ? $p['dev-master'] : current($p);
            //     $requires = isset($p['require']) ? array_keys($p['require']) : [];
            //     foreach ($requires as $require) {
            //         if (isset($packages[$require])) {
            //             unset($packages[$require]);
            //         }
            //     }
            // }

            return array_keys($packages);
        };

        if (in_array('tm/*', $packages)) {
            $packages = array_merge($packages, $requireall('tmhub'));
        } elseif (in_array('swissup/*', $packages)) {
            $packages = array_merge($packages, $requireall('swissup'));
        }
        $packages = array_diff($packages, ['tm/*', 'swissup/*']);

        foreach ($packages as &$package) {
            if (false === strpos($package, ':')) {
                $package .= ':*';
            }
        }
        $packages = array_unique($packages);
    }
    return array_filter($packages);
});

task('debug:option:packages', function () {
    $modules = get('option_packages_filtred');
    print_r($modules);
});
