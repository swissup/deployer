<?php

namespace Deployer;

use Deployer\Exception\GracefulShutdownException;

desc('Check Magento technology stack requirements');
task('magento2:deploy:check', function () {
    // date_default_timezone_set('America/New_York');
    // if (test("[ -h {{deploy_path}}/release ]")) {
    //     $release = run("readlink {{deploy_path}}/release");
    //     $release = explode('/', $release);
    //     $release = end($release);
    //     $release = substr($release, 4);
    //     $release = strtotime($release);

    //     if (14400 > abs(time() - $release)) {
    //         writeln("<error>Resource 'release' is busy</error>");
    //         throw new RuntimeException("Resource 'release' is busy\n");
    //         die;
    //     } else {
    //         writeln("<error>Resource 'release' is old and has been removed</error>");
    //         run("rm -rf {{deploy_path}}/release ");
    //     }
    // }
    $checked = "  <fg=green>✔</fg=green>";
    $notchecked = "  <fg=red>X</fg=red>";

    function check($command, $fingerprints)
    {
        $checked = "  <fg=green>✔</fg=green>";
        $notchecked = "  <fg=red>x</fg=red>";

        $result = run($command);
        $_result = strtolower($result);

        $status = false;
        foreach ($fingerprints as $fingerprint) {
            $fingerprint = strtolower($fingerprint);
            if (strstr($_result, $fingerprint)) {
                $status = true;
                $result = str_replace($fingerprint, "<fg=cyan>" . $fingerprint . "</fg=cyan>", $result);
            }
        }
        $status = $status ? $checked : $notchecked;
        writeln("\t" . $result . $status);
    }

    ////////////////////////////////////////////////////////////////////////////
    write("Operating systems :");
    check("uname -s ", ['Linux']);

    ////////////////////////////////////////////////////////////////////////////
    write("Web server:");
    $serverBin = run("{{bin/sudo}} lsof -i TCP:80 | grep LISTEN | head -1 | cut -d\  -f1");
    $serverBin = run("whereis {$serverBin} | cut -d\  -f2");
    check("{$serverBin} -v | head -1", [
        'Apache/2.2.',
        'Apache/2.4.',
        //'nginx/1.x' for > 2.2
        'nginx/1.8.',
        'nginx/1.9.',
        'nginx/1.10.',
        'nginx/1.11.',
        'nginx/1.12.',
        'nginx/1.13.',
        'nginx/1.14.',
        'nginx/1.15.',
    ]);

    ////////////////////////////////////////////////////////////////////////////
    write("Database:");
    check("{{bin/mysql}} --version", ['5.6.', '5.7.']);

    ////////////////////////////////////////////////////////////////////////////
    write("PHP: ");
    $req = [
        '2.0' => function ($phpVersion) {
            return ((version_compare($phpVersion, '5.5.22', '>=') && version_compare($phpVersion, '7.0.0', '<'))
                || version_compare($phpVersion, '7.0.2', '==')
                || (version_compare($phpVersion, '7.0.6', '>=') && version_compare($phpVersion, '7.1.0', '<')));
        },
        '2.1' => function ($phpVersion) {
            return ((version_compare($phpVersion, '5.6.5', '>=') && version_compare($phpVersion, '7.0.0', '<'))
                || version_compare($phpVersion, '7.0.2', '==')
                || version_compare($phpVersion, '7.0.4', '==')
                || (version_compare($phpVersion, '7.0.6', '>=') && version_compare($phpVersion, '7.1.0', '<')));
        },
        '2.2' => function ($phpVersion) {
            return ((version_compare($phpVersion, '7.0.13', '>=') && version_compare($phpVersion, '7.1.0', '<'))
                || version_compare($phpVersion, '7.1.0', '>=')
            );
        },
        '2.3' => function ($phpVersion) {
            return ((version_compare($phpVersion, '7.1.3', '>=') && version_compare($phpVersion, '7.2.0', '<'))
                || version_compare($phpVersion, '7.2.0', '>=')
            );
        },
        '2.4' => function ($phpVersion) {
            return (version_compare($phpVersion, '7.3.0', '>='));
        }
    ];
    if (input()->hasOption('tag')) {
        $tag = input()->getOption('tag');
    }
    if (empty($tag)) {
        $tag = get('magento2_repository_last_tag');
    }
    $tag = substr_replace($tag, '', -2);
    $phpVersion = run("{{bin/php}} -v | head -1 | cut -d\  -f2");
    if (isset($req[$tag]) && $req[$tag]($phpVersion)) {
        writeln($phpVersion . $checked);
    } else {
        writeln("\t" . run("{{bin/php}} -v | head -1") . $notchecked);
    }

    ////////////////////////////////////////////////////////////////////////////
    writeln("Required PHP extensions:");
    $phpModules = 'bcmath,ctype,curl,dom,gd,intl,mbstring,hash,openssl,pdo ,simplexml,soap,libxml,xsl,zip,json,iconv,spl';
    if (version_compare($phpVersion, '7.2.0', '<')) {
        $phpModules .= 'mcrypt';
    }

    if ($tag === '2.4') {
        $phpModules = 'bcmath,ctype,curl,dom,gd,intl,mbstring,hash,iconv,openssl,pdo_mysql,simplexml,soap,xsl,zip,libxml' ;
    }
    $phpModules = explode(',', $phpModules);
    foreach ($phpModules as $phpModule) {
        check("{{bin/php}} -m | awk '{print tolower($0)}' | grep {$phpModule}", [$phpModule]);
    }
})->setPrivate();

task('magento2:installed:check', function () {

    $exist = test("[ -f {{current_path}}/bin/magento ]");
    if (!$exist) {
        throw new GracefulShutdownException(
            "The script requires already installed \"Magento 2\"."
        );
    }
})->setPrivate();
