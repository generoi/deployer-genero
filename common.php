<?php

namespace Deployer;

use Deployer\Host\Localhost;
use Deployer\Task\Context;

desc('Open a remote SSH session');
task('ssh', function () {
    run('bash');
});

set('rsync_verify_options', function () {
    return '--checksum --dry-run --info=NAME ' . get('rsync_options');
});

/**
 * @see https://github.com/deployphp/deployer/blob/master/contrib/rsync.php
 */
task('rsync:verify', function () {
    $config = get('rsync');

    $src = get('rsync_src');
    while (is_callable($src)) {
        $src = $src();
    }

    if (!trim($src)) {
        // if $src is not set here rsync is going to do a directory listing
        // exiting with code 0, since only doing a directory listing clearly
        // is not what we want to achieve we need to throw an exception
        throw new \RuntimeException('You need to specify a source path.');
    }

    $dst = get('rsync_dest');
    while (is_callable($dst)) {
        $dst = $dst();
    }

    if (!trim($dst)) {
        // if $dst is not set here we are going to sync to root
        // and even worse - depending on rsync flags and permission -
        // might end up deleting everything we have write permission to
        throw new \RuntimeException('You need to specify a destination path.');
    }

    $host = Context::get()->getHost();
    // Original flags but ensure --verbose is not passed to it
    $flags = str_replace('v', '', $config['flags']);

    if ($host instanceof Localhost) {
        $rsyncCmd = "rsync -{$flags} {{rsync_verify_options}}{{rsync_includes}}{{rsync_excludes}}{{rsync_filter}} '$src/' '$dst/'";
    } else {
        $sshArguments = $host->connectionOptionsString();
        $rsyncCmd = "rsync -{$flags} -e 'ssh $sshArguments' {{rsync_verify_options}}{{rsync_includes}}{{rsync_excludes}}{{rsync_filter}} '$src/' '{$host->connectionString()}:$dst/'";
    }

    // Exit if there are any file changes reported
    runLocally("if [ $({$rsyncCmd} | wc -c) -ne 0 ]; then echo 'rsync integrity check failed'; exit 1; fi", $config);
});
