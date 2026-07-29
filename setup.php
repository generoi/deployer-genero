<?php

namespace Deployer;

desc('Setup initial deploy');
task('setup', [
    'scaffold:env',
    'scaffold:user_ini',
]);

desc('Scaffold an environment file');
task('scaffold:env', function () {
});

/*
 * PHP execution guard.
 *
 * Apache sites deny direct execution of non entry point .php files from
 * .htaccess. nginx ignores .htaccess entirely, so on nginx hosting (Kinsta)
 * that policy has to be enforced one layer down, in PHP, via auto_prepend_file.
 *
 * The guard script itself lives in the site repository, since the allowlist of
 * permitted entry points differs between sites. This task only wires it up.
 *
 * auto_prepend_file accepts an absolute path only -- no variables, and relative
 * paths resolve against the executing script's directory, which differs per
 * script. So the value cannot be committed and is scaffolded into shared/ like
 * .env, then symlinked into each release via shared_files:
 *
 *     set('shared_files', ['.env', 'web/.user.ini']);
 */
set('php_guard_file', 'config/php-guard.php');
set('php_guard_user_ini', '{{deploy_path}}/shared/web/.user.ini');

desc('Scaffold web/.user.ini enabling the PHP execution guard');
task('scaffold:user_ini', function () {
    $guard = get('php_guard_file');
    $file = get('php_guard_user_ini');

    // Nothing to wire up if the site does not ship a guard. Writing a
    // .user.ini pointing at a missing file would leave PHP logging a warning
    // on every request while silently protecting nothing.
    if (! test("[ -f {{deploy_path}}/current/$guard ]")) {
        writeln("<comment>Skipping: {$guard} not found in the current release.</comment>");

        return;
    }

    if (test("[ -s $file ]")) {
        if (! askConfirmation('web/.user.ini already exists, overwrite it?')) {
            return;
        }
    }

    // Points at current/ rather than a release path: this file is written once
    // and symlinked into every subsequent release.
    run('mkdir -p {{deploy_path}}/shared/web');
    run('printf "auto_prepend_file = %s\n" "{{deploy_path}}/current/' . $guard . '" > ' . $file);

    writeln('<info>Wrote ' . parse($file) . '</info>');
    writeln('<comment>PHP caches .user.ini for user_ini.cache_ttl, 300s on Kinsta.</comment>');
    writeln('<comment>Verify it is active: a missing or empty file fails silently.</comment>');
});
