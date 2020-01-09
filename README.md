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
```
