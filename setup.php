<?php

namespace Deployer;

desc('Setup initial deploy');
task('setup', [
    'deploy',
]);

desc('Scaffold an environment file');
task('scaffold:env', function () {
});

before('setup', 'scaffold:env');
