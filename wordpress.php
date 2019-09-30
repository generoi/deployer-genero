<?php

namespace Deployer;

set('cache_dir', get('cache_dir', 'web/app/cache'));

set('bin/wp', get('bin/wp', function () {
    return run('which wp');
}));

desc('Clear timber cache');
task('cache:clear:wp:timber', function () {
    run('cd {{deploy_path}}/current && rm -rf {{cache_dir}}/timber');
});

desc('Clear WP Super Cache cache');
task('cache:clear:wp:wpsc', function () {
    run('cd {{deploy_path}}/current && rm -rf {{cache_dir}}/blogs {{cache_dir}}/meta {{cache_dir}}/supercache {{cache_dir}}/wp-cache-*');
});

desc('Clear WP Object Cache');
task('cache:clear:wp:objectcache', function () {
    run('cd {{deploy_path}}/current && {{bin/wp}} cache flush --path=web/wp');
});

desc('Clear Acorn Caches');
task('cache:clear:wp:acorn', function () {
    run('cd {{deploy_path}}/current && {{bin/wp}} acorn optimize:clear --path=web/wp');
});

desc('Generate Acorn Caches');
task('cache:wp:acorn', function () {
    run('cd {{deploy_path}}/current && {{bin/wp}} acorn optimize --path=web/wp');
});

task('scaffold:env', function () {
    if (test('-f {{deploy_path}}/shared/.env')) {
        $confirm = askConfirmation('Environment file already exists, are you usre you want to rewrite it?');
        if (!$confirm) {
            return;
        }
    }

    $dbHost = ask('DB_HOST', 'localhost');
    $dbName = ask('DB_NAME', get('scaffold_machine_name', ''));
    $dbUser = ask('DB_USER', get('scaffold_machine_name', ''));
    $dbPassword = askHiddenResponse('DB_PASSWORD');
    $wpEnv = ask('WP_ENV', 'development');
    $wpHome = ask('WP_HOME', 'http://{{hostname}}');
    $domainCurrentSite = ask('DOMAIN_CURRENT_SITE', '{{hostname}}');

    upload(get('scaffold_env_file'), '{{deploy_path}}/shared/.env');
    run('sed -i "/^DB_HOST=/c\\DB_HOST=' . $dbHost . '" {{deploy_path}}/shared/.env');
    run('sed -i "/^DB_NAME=/c\\DB_NAME=' . $dbName . '" {{deploy_path}}/shared/.env');
    run('sed -i "/^DB_USER=/c\\DB_USER=' . $dbUser . '" {{deploy_path}}/shared/.env');
    run('sed -i "/^DB_PASSWORD=/c\\DB_PASSWORD=\'' . $dbPassword . '\'" {{deploy_path}}/shared/.env');
    run('sed -i "/^WP_ENV=/c\\WP_ENV=' . $wpEnv . '" {{deploy_path}}/shared/.env');
    run('sed -i "/^WP_HOME=/c\\WP_HOME=' . $wpHome . '" {{deploy_path}}/shared/.env');
    run('sed -i "/^DOMAIN_CURRENT_SITE=/c\\DOMAIN_CURRENT_SITE=' . $domainCurrentSite . '" {{deploy_path}}/shared/.env');

    foreach (['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'] as $key) {
        run('sed -i "/^' . $key . '=/c\\' . $key . '=\'' . hash('sha256', uniqid(rand(), true)) . '\'" {{deploy_path}}/shared/.env');
    }

    // @todo scaffold database.
});

desc('Clean directories which are emptied by dropin installer');
task('cleanup:dropin', function () {
    run('cd {{release_path}} && rm -rf vendor/koodimonni-language');
    run('cd {{release_path}} && rm -rf vendor/koodimonni-plugin-language');
    run('cd {{release_path}} && rm -rf vendor/koodimonni-theme-language');
});

/**
 * We need to prune the empty directories left by dropin installer or else
 * they wont be downloaded again.
 */
before('deploy:vendors', 'cleanup:dropin');
