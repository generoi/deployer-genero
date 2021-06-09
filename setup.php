<?php

namespace Deployer;

desc('Setup initial deploy');
task('setup', [
    'scaffold:env',
]);

desc('Scaffold an environment file');
task('scaffold:env', function () {
});
