<?php

namespace Deployer;

desc('Setup initial deploy');
task('setup', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',

    'scaffold:env',

    'build',

    'rsync:warmup',
    'rsync',

    'deploy:shared',
    'deploy:writable',
    'deploy:symlink',

    'deploy:unlock',
    'cleanup',
    'success',
]);

desc('Scaffold an environment file');
task('scaffold:env', function () {
});
