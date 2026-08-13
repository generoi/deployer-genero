<?php

namespace Deployer;

use function Deployer\Support\deployer_root;

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
 * deployer_root() is dirname(DEPLOYER_DEPLOY_FILE) — the directory holding
 * deploy.php — which is exactly what __DIR__ meant when this lived there. It is
 * also correct when dep is invoked from a subdirectory, and honours the
 * DEPLOYER_ROOT environment variable.
 *
 * Using __DIR__ here would resolve to vendor/generoi/deployer-genero instead.
 */
set('build_repository', function () {
    return deployer_root();
});

// Named after the project directory, so concurrent builds of different projects
// cannot collide in /tmp.
set('build_path', function () {
    return '/tmp/dep-'.basename(get('build_repository'));
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


/*
 * A sane default, in the same spirit as recipe/common.php shipping a minimal
 * `deploy` that recipe/laravel.php overrides.
 *
 * `build:theme` is not included: it is the one step that genuinely differs per
 * site (16 distinct implementations across the fleet). A project with a theme
 * build overrides this list and slots it in:
 *
 *     task('build', [
 *         'build:setup',
 *         'build:composer',
 *         'build:theme',
 *         'build:artifact',
 *     ]);
 */
desc('Build release locally');
task('build', [
    'build:setup',
    'build:composer',
    'build:artifact',
]);
