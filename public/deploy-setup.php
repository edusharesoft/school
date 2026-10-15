<?php
/**
 * One-time setup for shared hosting WITHOUT SSH/Terminal.
 *  1. Put DEPLOY_SETUP_TOKEN=<long random text> in .env
 *  2. Open https://scm.edushare.online/deploy-setup.php?token=<same text>
 *  3. DELETE this file afterwards (and remove the token from .env).
 */
define('LARAVEL_START', microtime(true));
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Read the token straight from .env so it still works after config:cache.
$envVars = Dotenv\Dotenv::parse((string) @file_get_contents(__DIR__.'/../.env'));
$token = (string) ($envVars['DEPLOY_SETUP_TOKEN'] ?? '');
if ($token === '' || ! hash_equals($token, (string) ($_GET['token'] ?? ''))) {
    http_response_code(404);
    exit('Not Found');
}

header('Content-Type: text/plain; charset=utf-8');
echo 'PHP '.PHP_VERSION.(version_compare(PHP_VERSION, '8.2.0', '>=') ? ' OK' : '  <-- NEED PHP 8.2 OR NEWER')."\n\n";

$commands = [
    ['optimize:clear', []],
    ['storage:link', ['--force' => true]],
];
if (($_GET['migrate'] ?? '') === '1') {
    $commands[] = ['migrate', ['--force' => true]];
}
$commands[] = ['config:cache', []];
$commands[] = ['route:cache', []];
$commands[] = ['view:cache', []];

foreach ($commands as [$cmd, $args]) {
    echo "> php artisan $cmd\n";
    try {
        $kernel->call($cmd, $args);
        echo $kernel->output()."\n";
    } catch (Throwable $e) {
        echo 'ERROR: '.$e->getMessage()."\n\n";
    }
}

try {
    Illuminate\Support\Facades\DB::connection()->getPdo();
    echo "Database connection: OK\n";
} catch (Throwable $e) {
    echo 'Database connection FAILED: '.$e->getMessage()."\n";
}
echo "\nDone. Now DELETE public/deploy-setup.php from the server.\n";
