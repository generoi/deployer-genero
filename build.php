<?php

namespace Deployer;

/**
 * Shared local-build implementation.
 *
 *     import('vendor/generoi/deployer-genero/build.php');
 *
 * Only the implementations live here. The `build` and `deploy` task *lists*
 * stay in each project's deploy.php on purpose: they document that project's
 * pipeline, and reading deploy.php should tell you what a deploy does.
 *
 * `build:theme` and `cache:clear` also stay per project — they genuinely
 * differ (16 and 14 distinct variants across 47 sites), unlike the tasks below,
 * which were byte-identical everywhere apart from formatting.
 *
 * Any setting below can be overridden per project, e.g. a site that needs an
 * extra path kept out of the artifact re-declares `build_artifact_exclude`.
 */

/*
 * Resolved lazily on purpose: __DIR__ in this file is the package directory
 * inside vendor/, not the project root, so the value must be computed when the
 * task runs rather than when the file is imported.
 */
set('build_repository', function () {
    return getcwd();
});

set('build_path', function () {
    return '/tmp/dep-'.basename(getcwd());
});

set('build_artifact_dir', '{{build_path}}/artifact');

set('build_artifact_exclude', [
    '.git',
    'node_modules',
    '*.sql',
    '/.*',
    '/*.md',
    '/config/*.yml',
    '/config/patches',
    '/*.php',
    '/*.xml',
    '/*.yml',
]);

desc('Clone the working tree into a clean build path');
task('build:setup', function () {
    runLocally('rm -rf {{build_path}}');
    runLocally('mkdir -p {{build_path}}');
    runLocally('{{bin/git}} clone {{build_repository}} {{build_path}}');
});

desc('Install Composer dependencies into the build');
task('build:composer', function () {
    runLocally('cd {{build_path}} && {{bin/composer}} {{composer_action}} {{composer_options}}');
});

desc('Copy the build into a sanitised artifact directory');
task('build:artifact', function () {
    $exclude = array_reduce(get('build_artifact_exclude'), function ($carry, $exclude) {
        return $carry.' --exclude='.escapeshellarg($exclude);
    }, '');

    runLocally("rsync -r --delete --links $exclude '{{build_path}}/' '{{build_artifact_dir}}/'");
});

task('deploy:update_code', function () {
    // Nothing to do: the git repository is never stored on the remote, the
    // sanitised artifact is rsynced instead.
});
