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
 * On Kinsta the FPM socket is found automatically (see `cachetool_args` below).
 * Elsewhere each site sets its own `cachetool_args` with the FPM address, since
 * that is genuinely per-host, e.g.
 *
 *     ->set('cachetool_args', '--fcgi=127.0.0.1:11008 --tmp-dir={{deploy_path}}')
 *
 * Then add `cache:clear:opcache` to the site's `cache:clear`.
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
        run("curl -fsSL --retry 3 {{cachetool_url}} -o $binary");
    }

    return $binary;
});

/**
 * Kinsta: find the site's FPM socket at run time. Its name carries the PHP
 * version (`/var/run/php8.5-fpm-<site>.sock`) and Kinsta updates PHP
 * automatically, so a hardcoded path silently stops matching. Each Kinsta
 * container has only its own site's socket. Sites that set `cachetool_args`
 * themselves override this.
 */
set('cachetool_args', function () {
    $sockets = array_filter(explode("\n", run('ls -1 /var/run/php*-fpm-*.sock 2>/dev/null || true')));

    if (count($sockets) !== 1) {
        throw new \RuntimeException(sprintf('cachetool: expected one PHP-FPM socket in /var/run, found %d; set cachetool_args for this host', count($sockets)));
    }

    return '--fcgi='.trim(reset($sockets)).' --tmp-dir={{deploy_path}}';
});

/**
 * Kinsta does not clear OPcache on deploy, and each release is cached under its
 * own `releases/NNN` path, so old releases' entries stay until PHP restarts.
 * A few deploys fill the cache; requests then compile the code themselves and
 * run slow or out of memory. On suomentyokalu (2026-11) the 640 MiB cache held
 * ~4.5 releases with 40 bytes free and a 36% hit rate, and boot alone used
 * ~230 MB of the 256 MB limit.
 *
 * Best-effort, so a missing socket can't fail a deploy, but warned loudly.
 */
desc('Reset FPM OPcache, without failing the deploy');
task('cache:clear:opcache', function () {
    try {
        invoke('cachetool:clear:opcache');
    } catch (\Throwable $e) {
        warning('cachetool:clear:opcache failed, old releases stay in OPcache: '.$e->getMessage());
    }
});
