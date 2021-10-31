<?php
namespace Deployer;

require 'recipe/common.php';

$deployPath = '/home/magento_user/var/www/{{application}}';
$contentVersion = time();

host('49.12.197.154')
    ->stage('production')
    ->user('magento_user')
    ->identityFile('~/.ssh/id_rsa')
    ->port('22')
    ->multiplexing(true)
    ->set('deploy_path', $deployPath)
    ->set('http_user', 'magento_user')
    ->set('branch', 'master')
    ->set('nfs_root', '/home/magento_user/var/mnt/nfs')
    ->set('nfs_dirs', ['pub/media'])
    ->forwardAgent()
    ->addSshOption('StrictHostKeyChecking', 'no')

;
// Project name
set('application', 'magento_pwa');
set('default_timeout', 9999);
set('default_stage', 'production');

// Project repository
set('repository', 'git@control.scandesigns.dk:magento_pwa_test_server/test_server_pwa.git');

// [Optional] Allocate tty for git clone. Default value is false.
set('git_tty', true);

set('allow_anonymous_stats', false);
set('writable_mode', 'chmod');
set('writable_chmod_mode', 775);
set('keep_releases', 3);
set('writable_use_sudo', true);
set('clear_use_sudo', true);
set('cleanup_use_sudo', true);
set('ssh_multiplexing', true);
// Configuration
set('static_content_locales', 'en_US');
// Writable dirs by web server
set('writable_dirs', [
    'var',
    'pub/static',
    'generated',
    'app/etc',
    'var/cache',
    'var/page_cache',
    'var/view_preprocessed',
    'var/log/varnish',
    'var/profiler'
]);

set('clear_paths', [
    'generated/*',
    'pub/static/_cache/*',
    'var/generation/*',
    'var/page_cache/*',
    'var/view_preprocessed/*'
]);

// Tasks
desc('Current time');
task('notify:time', function () {
    writeln(
        date('m/d/Y h:i:s a', time())
    );;
});

desc('Stop cron');
task('cron:stop', function () {
    run("sudo systemctl stop cron.service");
});

desc('Copies ENV');
task('magento:copy:env', function () {
    run("cp -p {{deploy_path}}/env.php {{release_path}}/app/etc/env.php");
});

desc('deploy vendors');
task('deploy:vendors', function () {
    cd('{{release_path}}');
    run("{{bin/composer}} install --no-dev");
});

desc('Module enable');
task('magento:module:enable', function () {
    run("{{bin/php}} {{release_path}}/bin/magento module:enable --all");
});

desc('Production mode');
task('magento:production:mode', function () {
    run("{{bin/php}} {{release_path}}/bin/magento deploy:mode:set production -s");
});

desc('Compile magento di');
task('magento:compile', function () {
    run("{{bin/php}} {{release_path}}/bin/magento setup:di:compile");
    run('cd {{release_path}} && {{bin/composer}} dump-autoload -o --apcu');
});

desc('Deploy assets');
task('magento:deploy:assets', function () use ($contentVersion) {
    run(
        "{{bin/php}} {{release_path}}/bin/magento setup:static-content:deploy {{static_content_locales}}"
        . " --jobs 50"
        . " --content-version=" . $contentVersion
    );
});

desc('Enable maintenance mode');
task('magento:maintenance:enable', function () {
    run("if [ -d $(echo {{release_path}}) ]; then {{bin/php}} {{release_path}}/bin/magento maintenance:enable; fi");
});

desc('Upgrade magento database');
task('magento:upgrade:db', function () {
    run("{{bin/php}} {{release_path}}/bin/magento setup:upgrade --keep-generated");
});

desc('Flush Magento Cache');
task('magento:cache:flush', function () {
    run("{{bin/php}} {{release_path}}/bin/magento cache:flush");
});

desc('Disable maintenance mode');
task('magento:maintenance:disable', function () {
    run("if [ -d $(echo {{deploy_path}}) ]; then {{bin/php}} {{release_path}}/bin/magento maintenance:disable; fi");
});

desc('Magento2 deployment operations');
task('deploy:magento', [
    'magento:module:enable',
    'magento:copy:env',
    'magento:production:mode',
    'magento:compile',
    'magento:deploy:assets',
    'magento:maintenance:enable',
    'magento:upgrade:db',
    'magento:cache:flush',
    'magento:maintenance:disable',
]);

desc('Change Chmod');
task('magento:change:chmod', function () {
    run("chmod 775 -R {{release_path}}/generated");
    run("chmod 775 -R {{release_path}}/var");
    run("chmod 775 -R {{release_path}}/pub/static");
});

desc('Start cron');
task('cron:start', function () {
    run("sudo systemctl start cron.service");
});


desc('Deploy your project');
task('deploy', [
    'deploy:unlock',
    'cron:stop',
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:writable',
    'deploy:vendors',
    'deploy:clear_paths',
    'deploy:magento',
    'magento:change:chmod',
    'deploy:symlink',
    'deploy:unlock',
    'cron:start',
    'success'
]);

before('deploy', 'notify:time');
