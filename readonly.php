<?php

namespace Deployer;

desc('Make filesystem read-only');
task('readonly', function () {
    $dirs = join(' ', get('writable_dirs'));

    cd('{{release_path}}');
    // Use find instead
    run('chmod -R a-w .');
    run("chmod -R {{writable_chmod_mode}} $dirs");
});


desc('Make expired releases writable so they can be removed');
task('readonly:cleanup:writable', function () {
    $releases = get('releases_list');
    $keep = get('keep_releases');

    if ($keep === -1) {
        // Keep unlimited releases.
        return;
    }

    while ($keep > 0) {
        array_shift($releases);
        --$keep;
    }

    foreach ($releases as $release) {
        run("chmod -R ug+w {{deploy_path}}/releases/$release");
    }
});

desc('Make any unfinished release writable so that it can be removed');
task('readonly:release:writable', function () {
    cd('{{deploy_path}}');
    // If there is unfinished release, make it writable so it can be removed.
    if (test('[ -h release ] && [ -e release ]')) {
        run('chmod -R ug+w "$(readlink release)"');
    }
});
