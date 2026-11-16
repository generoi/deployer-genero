<?php

namespace Deployer;

/**
 * Page and edge cache purging on deploy, set by `purge`:
 *
 * - `soft` (default): run every task in `purge_tasks`. Soft-purge where the CDN
 *   can (sage-cachetags with a soft invalidator), so visitors are served the old
 *   page while it revalidates; Kinsta has no soft purge, so it is always emptied.
 * - `hard`: the same, but `wp cachetags flush --hard`, so nothing old is served.
 * - `auto`: `hard` when the built assets changed, otherwise nothing. Use it where
 *   a deploy that only changes PHP doesn't need every page re-rendered.
 * - `none`: purge nothing.
 *
 * Rollbacks don't check the assets, so under `auto` they always purge.
 *
 *     import('vendor/generoi/deployer-genero/purge.php');
 *
 *     set('purge', 'auto');
 *     set('purge_tasks', ['cache:clear:kinsta', 'cache:clear:cachetags']);
 *     set('assets_dirs', ['{{theme_dir}}/build']);
 *
 *     task('cache:clear', [
 *         'cache:wp:acorn',
 *         'cache:clear:wp:objectcache:only',
 *         'cache:clear:opcache',
 *         'cache:purge',
 *     ]);
 *
 * A deploy overrides the site's mode with `dep deploy production -o purge=hard`.
 */
set('purge', 'soft');
set('purge_tasks', []);
set('assets_dirs', ['{{theme_dir}}/build']);
set('assets_changed', true);
set('purge_hard', false);

function purgeMode(): string
{
    $mode = get('purge');

    if (! in_array($mode, ['auto', 'none', 'soft', 'hard'], true)) {
        throw new \RuntimeException("purge must be auto, none, soft or hard, not '$mode'");
    }

    return $mode;
}

/**
 * Checksum of the built files under `assets_dirs` in one release. A missing
 * directory hashes as empty, so the first deploy counts as a change.
 */
function assetsChecksum(string $root): string
{
    $dirs = implode(' ', array_map(fn ($dir) => escapeshellarg(parse($dir)), get('assets_dirs')));

    return run("{ cd $root && find $dirs -type f -exec md5sum {} +; } 2>/dev/null | LC_ALL=C sort | md5sum");
}

desc('Check whether the built assets changed');
task('cache:check_assets', function () {
    // Fail an invalid mode before the release goes live, not after.
    purgeMode();

    foreach (get('assets_dirs') as $dir) {
        if (! test('[ -d {{release_path}}/'.$dir.' ]')) {
            warning(parse("assets_dirs: $dir isn't in the release, so every deploy purges"));
            set('assets_changed', true);

            return;
        }
    }

    set('assets_changed', assetsChecksum('{{current_path}}') !== assetsChecksum('{{release_path}}'));
});

desc('Flush the object cache, without purging the page cache');
task('cache:clear:wp:objectcache:only', function () {
    // Not `wp cache flush`: kinsta-mu-plugins hooks that command and purges the
    // whole Kinsta page cache after it.
    run('cd {{deploy_path}}/current/web && {{bin/wp}} eval "wp_cache_flush();"');
});

desc('Flush sage-cachetags (Cloudflare, Fastly, …)');
task('cache:clear:cachetags', function () {
    $hard = get('purge_hard') ? ' --hard' : '';
    run("cd {{deploy_path}}/current/web && {{bin/wp}} cachetags flush$hard");
});

desc('Purge the page and edge caches as `purge` says');
task('cache:purge', function () {
    $mode = purgeMode();

    if ($mode === 'auto') {
        $mode = get('assets_changed') ? 'hard' : 'none';
    }

    info("purge: $mode");

    if ($mode === 'none') {
        return;
    }

    set('purge_hard', $mode === 'hard');

    // Best-effort: a CDN hiccup must not fail a deploy whose release is live.
    foreach (get('purge_tasks') as $task) {
        try {
            invoke($task);
        } catch (\Throwable $e) {
            warning("$task failed, pages stay cached until they expire: ".$e->getMessage());
        }
    }
});

before('deploy:symlink', 'cache:check_assets');
