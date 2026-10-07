<?php

use App\Services\CheckoutService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! app()->environment('testing') || config('database.default') !== 'pgsql' || ! str_ends_with(config('database.connections.pgsql.database'), '_test')) {
    fwrite(STDERR, 'This worker only runs against an explicitly named PostgreSQL test database.');
    exit(2);
}

[$script, $gateFile, $key, $encodedPayload] = $argv;
echo "READY\n";
flush();
$deadline = microtime(true) + 15;
while (! is_file($gateFile)) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Concurrent checkout barrier timed out.');
    }
    usleep(10000);
}

try {
    [$order, $created] = app(CheckoutService::class)->create(json_decode(base64_decode($encodedPayload), true, flags: JSON_THROW_ON_ERROR), $key, null);
    echo json_encode(['status' => $created ? 201 : 200, 'id' => $order->id], JSON_THROW_ON_ERROR)."\n";
} catch (ValidationException $exception) {
    echo json_encode(['status' => 422, 'errors' => $exception->errors()], JSON_THROW_ON_ERROR)."\n";
} catch (ConflictHttpException $exception) {
    echo json_encode(['status' => 409], JSON_THROW_ON_ERROR)."\n";
}
