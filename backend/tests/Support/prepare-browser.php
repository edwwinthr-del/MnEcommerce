<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! app()->environment('testing') || ! str_ends_with(DB::connection()->getDatabaseName(), '_test')) {
    throw new RuntimeException('Browser fixtures require APP_ENV=testing and a disposable *_test database.');
}

if (in_array('--clear-rate-limits', $argv, true)) {
    if (config('cache.default') !== 'database' || config('cache.stores.database.connection') !== null) {
        throw new RuntimeException('Browser cache reset requires the default disposable database cache.');
    }
    Cache::flush();
    exit(0);
}

$email = getenv('E2E_ADMIN_EMAIL');
$password = getenv('E2E_ADMIN_PASSWORD');
if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $password || strlen($password) < 14) {
    throw new RuntimeException('Set E2E_ADMIN_EMAIL and E2E_ADMIN_PASSWORD (14+ characters) in the test process.');
}

// Validate the resolved connection before resetting anything, not just environment input.
if (in_array('--reset', $argv, true)) {
    Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
    echo Artisan::output();
}

$admin = User::firstOrNew(['email' => $email]);
$admin->forceFill([
    'name' => 'Browser Test Administrator',
    'role' => 'admin',
    'password' => $password,
    'email_verified_at' => now(),
])->save();

echo "Browser test administrator prepared in the disposable test database.\n";
