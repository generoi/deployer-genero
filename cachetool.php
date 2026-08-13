<?php

namespace Deployer;

/**
 * Shared cachetool configuration.
 *
 * Import alongside Deployer's own recipe, after it so these win:
 *
 *     import('contrib/cachetool.php');
 *     import('vendor/generoi/deployer-genero/cachetool.php');
 *
 * Each site still sets its own `cachetool_args` with the FPM address, since
 * that is genuinely per-host, e.g.
 *
 *     ->set('cachetool_args', '--fcgi=127.0.0.1:11008 --tmp-dir={{deploy_path}}')
 *
 * Clearing FPM's opcache is not optional on these servers: with
 * `opcache.revalidate_path=0`, opcache resolves the path through the `current`
 * symlink to its already-cached entry and never re-resolves it, so
 * `validate_timestamps` checks the previous release's file, finds it unchanged,
 * and keeps serving stale bytecode after a deploy.
 */
set('cachetool_version', '10.0.0');
set('cachetool_url', 'https://github.com/gordalina/cachetool/releases/download/{{cachetool_version}}/cachetool.phar');

/**
 * Cache the phar per version in the persistent `.dep` directory instead of the
 * release directory, so it is fetched once per version rather than once per
 * deploy. On 2026-08-12 GitHub throttled a fleet-wide deploy and the
 * per-release download broke production deploys; with this, a GitHub outage can
 * only affect the first deploy after a version bump.
 */
set('bin/cachetool', function () {
    $binary = parse('{{deploy_path}}/.dep/cachetool-{{cachetool_version}}.phar');

    if (! test("[ -f $binary ]")) {
        run('mkdir -p {{deploy_path}}/.dep');
        run("curl -fsSL --retry 3 --retry-all-errors {{cachetool_url}} -o $binary");
    }

    return $binary;
});
