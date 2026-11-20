# deployer-genero

Genero tasks for Deployer.

This deploy workflow clones the local (or remote) git repository into `.build/`, builds it from scratch and then creates a sanitized artifact in `.build/artifact` which is rsynced to the remote host.

### Installation

    composer require generoi/deployer-genero --dev

### Example `deploy.php`

```php
namespace Deployer;

require 'recipe/wordpress.php';
require 'recipe/cachetool.php';
require 'recipe/rsync.php';
require 'recipe/deploy/rollback.php';
require 'vendor/generoi/deployer-genero/common.php';
require 'vendor/generoi/deployer-genero/build.php';
require 'vendor/generoi/deployer-genero/setup.php';
require 'vendor/generoi/deployer-genero/wordpress.php';

set('scaffold_machine_name', 'project-name');
set('scaffold_env_file', __DIR__ . '/.env.example');
set('theme_dir', 'web/app/themes/project-name');

set('shared_files', ['.env']);
set('shared_dirs', ['web/app/uploads', '{{cache_dir}}']);
set('writable_dirs', get('shared_dirs'));

set('bin/robo', './vendor/bin/robo');
set('bin/wp', './vendor/bin/wp');
set('bin/npm', function () {
    return run('which npm');
});

/**
 * Build configuration
 */
set('build_repository', __DIR__);
set('build_shared_dirs', ['{{theme_dir}}/node_modules']);
set('build_copy_dirs', ['{{theme_dir}}/vendor', 'vendor']);
set('build_path', __DIR__ . '/.build');
set('build_artifact_dir', '{{build_path}}/artifact');
set('build_artifact_exclude', [
    '.git',
    'node_modules',
    '*.sql',
    '/.*',
    '/*.md',
    '/config/*.yml',
    '/config/patches',
    '/composer.json',
    '/composer.lock',
    '/*.php',
    '/*.xml',
    '/*.yml',
    '/Vagrantfile*',
]);

/**
 * Deploy configuration
 */
set('rsync_src', '{{build_artifact_dir}}');
set('rsync_dest', '{{release_path}}');
set('rsync', [
    'exclude'       => [],
    'include'       => [],
    'filter'        => [],
    'exclude-file'  => false,
    'include-file'  => false,
    'filter-file'   => false,
    'filter-perdir' => false,
    'flags'         => 'rv',
    'options'       => ['delete'],
    'timeout'       => 3600,
]);

/**
 * Deploy
 */
desc('Clear caches');
task('cache:clear', [
    'cache:clear:wp:timber',
    'cache:clear:wp:wpsc',
    'cache:clear:wp:objectcache',
    'cachetool:clear:opcache',
    // 'cachetool:clear:apc',
]);

task('build:assets', function () {
    if (!get('use_quick')) {
        run('cd {{release_path}}/{{theme_dir}} && {{bin/composer}} {{composer_options}}');
        run('cd {{release_path}}/{{theme_dir}} && {{bin/npm}} install --no-audit', ['timeout' => 1000]);
    }
    run('cd {{release_path}}/{{theme_dir}} && {{bin/npm}} run lint');
    run('cd {{release_path}} && {{bin/robo}} build:production');
});

desc('Deploy release');
task('deploy', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',

    'build',

    'rsync:warmup',
    'rsync',

    'deploy:shared',
    'deploy:writable',
    'deploy:symlink',

    'cache:clear',

    'deploy:unlock',
    'cleanup',
    'success',
]);
```

### Purging page caches on deploy

`purge.php` purges the page and edge caches as the `purge` setting says:
`soft` (the default), `hard`, `auto` (only when the built assets or
`composer.lock` changed) or `none`. A site sets its own default in
`deploy.php`, and a deploy overrides it with `-o purge=hard`. The modes and the
setup are documented in [`purge.php`](purge.php).

To purge a live site without deploying, run the task on its own from the site's
checkout. It uses the site's own `purge_tasks`, so the same command covers
Kinsta, Cloudflare and Fastly:

```sh
vendor/bin/dep cache:purge production -o purge=hard
```

Across many sites, run it in each checkout; a site without `purge.php` has no
`cache:purge` task and fails, which marks it as one to migrate. After a
dependency rollout that changes markup (a form plugin's hidden fields, say),
purge `hard`: a soft purge can serve the old page once more per URL.

### Database updates on deploy

`wp:update` in `wordpress.php` runs the WP-CLI commands in `wp_update_commands`,
by default `wp core update-db`, so a WordPress core update no longer needs a
manual `wp core update-db` on each environment. On a multisite network it adds
`--network` and upgrades every site. Each command is a no-op when the database
is current, so it is safe on every deploy. Hook it in `deploy.php` after the
symlink and before the cache clearing:

```php
after('deploy:symlink', 'wp:update');
after('deploy:symlink', 'cache:clear');
```

After the symlink because WordPress upgrades the database from the new code,
the way `wp-admin/upgrade.php` does; the old code keeps working against the old
schema until then. Before `cache:clear` so the caches are rebuilt and pages
purged against the upgraded database. A failed command fails the deploy: the
release is already live but wp-admin asks for a database update, so fix the
cause and run `vendor/bin/dep wp:update production` and `vendor/bin/dep
cache:clear production`, or redeploy.

A site adds its own commands, each one that must also be a no-op when there is
nothing to do. A string runs as is, on a network for the main site only; an
entry `['cmd' => '…', 'network' => '…']` appends `network` on a multisite
network, as the default `core update-db --network` does:

```php
add('wp_update_commands', ['wc update']);
```

### Provided commands

```sh
  build                       Build release locally
  setup                       Setup initial deploy
  ssh                         Connect to host through ssh

 build
  build:artifact              Build a release artifact
  build:assets

 cache
  cache:clear                 Clear caches
  cache:clear:wp:objectcache  Clear WP Object Cache
  cache:clear:wp:timber       Clear timber cache
  cache:clear:wp:wpsc         Clear WP Super Cache cache

 cleanup
  cleanup:dropin              Clean directories which are emptied by dropin installer

 scaffold
  scaffold:env

 wp
  wp:update                   Run the database updates the deployed code needs
```
