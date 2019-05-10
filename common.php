<?php

namespace Deployer;

desc('Open a remote SSH session');
task('ssh', function () {
    run('bash');
});

desc('Clear caches');
task('cache:clear', []);
