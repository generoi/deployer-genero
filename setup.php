<?php

namespace Deployer;

desc('Setup initial deploy');
task('setup', [
    'wordpress:set-wp-home',
    'scaffold:env',
]);

desc('Scaffold an environment file');
task('scaffold:env', function () {
});
