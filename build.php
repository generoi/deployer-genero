<?php

namespace Deployer;

use Symfony\Component\Console\Input\InputOption;

/**
 * Path where build files will be located
 * @required
 */
set('build_path', null);

/**
 * Repository to clone during the build step.
 * @required
 */
set('build_repository', null);

/**
 * Directories which can be symlinked between releases.
 */
set('build_shared_dirs', ['node_modules']);

/**
 * Directories which have to be rebuilt between releases.
 */
set('build_copy_dirs', ['vendor']);


/**
 * Directory where build artifact will be located.
 */
set('build_artifact_dir', '{{build_path}}/artifact');

/**
 * Files which will be excluded from the build artifact that's deployed.
 */
set('build_artifact_exclude', [
    '.git',
    'node_modules',
    '*.sql',
    '/.*',
    '/*.md',
    '/composer.json',
    '/composer.lock',
    '/*.php',
    '/*.xml',
    '/*.yml',
    '/Vagrantfile*',
]);

/**
 * --quick flag to skip some time consuming tasks that aren't always needed.
 */
option('quick', null, InputOption::VALUE_NONE, 'Skip time consuming tasks that are only used for edge cases');
set('use_quick', function () {
    return input()->getOption('quick') && has('previous_release');
});

desc('Build a release artifact');
task('build:artifact', function () {
    set('rsync', [
        'exclude'       => get('build_artifact_exclude'),
        'include'       => [],
        'filter'        => [],
        'exclude-file'  => false,
        'include-file'  => false,
        'filter-file'   => false,
        'filter-perdir' => false,
        'flags'         => 'r', // Recursive
        'options'       => ['delete'],
        'timeout'       => 3600,
    ]);

    set('rsync_src', '{{release_path}}');
    set('rsync_dest', '{{build_artifact_dir}}');
    invoke('rsync');
});

desc('Build release locally');
task('build', function () {
    set('repository', get('build_repository'));
    set('deploy_path', get('build_path'));
    set('keep_releases', 1);
    set('shared_files', []);
    set('shared_dirs', get('build_shared_dirs'));
    set('copy_dirs', get('build_copy_dirs'));

    invoke('cleanup');
    invoke('deploy:prepare');
    invoke('deploy:release');
    invoke('deploy:update_code');
    invoke('deploy:copy_dirs');
    invoke('deploy:shared');
    invoke('deploy:vendors');

    // Allow eg. theme assets to be built.
    invoke('build:assets');

    invoke('deploy:symlink');
    invoke('build:artifact');
    invoke('cleanup');
})->local();

desc('Build project assets');
task('build:assets', function () {
});
