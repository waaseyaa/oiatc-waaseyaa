<?php

namespace Deployer;

require 'recipe/common.php';

set('application', 'oiatc-waaseyaa');
set('keep_releases', 5);
set('allow_anonymous_stats', false);

set('shared_dirs', ['storage']);
set('shared_files', ['.env']);
set('writable_dirs', ['storage', 'storage/framework']);

host('production')
    ->setHostname('147.182.150.145')
    ->set('remote_user', 'deployer')
    ->set('deploy_path', '/home/deployer/oiatc-waaseyaa')
    ->set('labels', ['stage' => 'production']);

desc('Upload pre-built release artifact from CI');
task('deploy:upload', function (): void {
    upload('.build/', '{{release_path}}/', [
        'options' => ['--recursive', '--compress'],
    ]);
});

desc('Clear Waaseyaa framework manifest cache');
task('waaseyaa:clear-manifest', function (): void {
    run('rm -f {{release_path}}/storage/framework/packages.php');
});

desc('Reload PHP-FPM to pick up new release');
task('php-fpm:reload', function (): void {
    run('sudo systemctl reload php8.4-fpm');
});

desc('Deploy oiatc.waaseyaa.org to production');
task('deploy', [
    'deploy:info',
    'deploy:setup',
    'deploy:lock',
    'deploy:release',
    'deploy:upload',
    'deploy:shared',
    'deploy:writable',
    'waaseyaa:clear-manifest',
    'deploy:symlink',
    'deploy:unlock',
    'deploy:cleanup',
    'php-fpm:reload',
]);

after('deploy:failed', 'deploy:unlock');
