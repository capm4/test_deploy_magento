<?php
namespace Deployer;

$deployPath = '/var/www/{{application}}';
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

desc('deploy vendors');
task('deploy:vendors', function () {
    cd('{{deploy_path}}');
    run("{{bin/composer}} install --no-dev");
});

desc('Production mode');
task('magento:production:mode', function () {
    run("{{bin/php}} {{deploy_path}}/bin/magento deploy:mode:set production -s");
});

desc('Compile magento di');
task('magento:compile', function () {
    run("{{bin/php}} {{deploy_path}}/bin/magento setup:di:compile");
    run('cd {{deploy_path}} && {{bin/composer}} dump-autoload -o --apcu');
});

desc('Deploy assets');
task('magento:deploy:assets', function () use ($contentVersion) {
    run(
        "{{bin/php}} {{deploy_path}}/bin/magento setup:static-content:deploy {{static_content_locales}}"
        . " --jobs 50"
        . " --content-version=" . $contentVersion
    );
});

desc('Enable maintenance mode');
task('magento:maintenance:enable', function () {
    run("if [ -d $(echo {{deploy_path}}) ]; then {{bin/php}} {{deploy_path}}/bin/magento maintenance:enable; fi");
});

desc('Upgrade magento database');
task('magento:upgrade:db', function () {
    run("{{bin/php}} {{deploy_path}}/bin/magento setup:upgrade --keep-generated");
});

desc('Flush Magento Cache');
task('magento:cache:flush', function () {
    run("{{bin/php}} {{deploy_path}}/bin/magento cache:flush");
});

desc('Disable maintenance mode');
task('magento:maintenance:disable', function () {
    run("if [ -d $(echo {{deploy_path}}) ]; then {{bin/php}} {{deploy_path}}/bin/magento maintenance:disable; fi");
});

desc('Magento2 deployment operations');
task('deploy:magento', [
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
    run("sudo chmod 775 -R {{deploy_path}}/generated");
    run("sudo chmod 775 -R {{deploy_path}}/var");
    run("sudo chmod 775 -R {{deploy_path}}/pub/static");
});

desc('Start cron');
task('cron:start', function () {
    run("sudo systemctl start cron.service");
});


desc('Deploy your project');
task('deploy', [
    'cron:stop',
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:update_code',
    'deploy:writable',
    'deploy:vendors',
    'deploy:clear_paths',
    'deploy:magento',
    'magento:change:chmod',
    'deploy:unlock',
    'cron:start',
    'success'
]);

before('deploy', 'notify:time');

after('deploy:failed', 'magento:maintenance:disable');
after('deploy:failed', 'cron:start');

after('success', 'restart:services');
after('success', 'cleanup');
after('success', 'notify:time');
