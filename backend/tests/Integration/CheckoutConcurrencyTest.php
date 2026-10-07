<?php

namespace Tests\Integration;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Separate integration suite: uses committed fixtures and two independent PHP/PG connections. */
class CheckoutConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Real concurrency verification requires PostgreSQL.');
        }

        $this->assertTrue(app()->environment('testing'));
        $this->assertStringEndsWith('_test', DB::connection()->getDatabaseName(), 'Use a disposable database with the _test suffix.');
        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    public function test_simultaneous_requests_cannot_oversell_last_item(): void
    {
        $product = $this->product(1);
        $results = $this->race($this->payload($product), [(string) Str::uuid(), (string) Str::uuid()]);
        $this->assertSame([201, 422], $this->statuses($results));
        $this->assertSame(0, $product->fresh()->stock);
        $this->assertSame(1, Order::count());
    }

    public function test_simultaneous_requests_cannot_exceed_coupon_limit(): void
    {
        $product = $this->product(5);
        $coupon = Coupon::create(['code' => 'LASTONE', 'type' => 'fixed', 'amount' => 100, 'usage_limit' => 1]);
        $results = $this->race([...$this->payload($product), 'coupon_code' => $coupon->code], [(string) Str::uuid(), (string) Str::uuid()]);
        $this->assertSame([201, 422], $this->statuses($results));
        $this->assertSame(1, $coupon->fresh()->usage_count);
        $this->assertSame(4, $product->fresh()->stock);
        $this->assertSame(1, Order::count());
    }

    public function test_simultaneous_identical_idempotency_keys_create_only_one_order(): void
    {
        $product = $this->product(5);
        $key = (string) Str::uuid();
        $results = $this->race($this->payload($product), [$key, $key]);
        $this->assertSame([200, 201], $this->statuses($results));
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertSame(4, $product->fresh()->stock);
        $this->assertSame(1, Order::count());
    }

    private function race(array $payload, array $keys): array
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'mora-checkout-race-'.Str::uuid();
        mkdir($directory, 0700);
        $gateFile = $directory.DIRECTORY_SEPARATOR.'start';
        $database = config('database.connections.pgsql');
        $environment = [
            'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'pgsql', 'DB_HOST' => $database['host'], 'DB_PORT' => (string) $database['port'],
            'DB_DATABASE' => $database['database'], 'DB_USERNAME' => $database['username'], 'DB_PASSWORD' => (string) $database['password'],
            'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
        ];
        $processes = [];

        try {
            foreach ($keys as $key) {
                $command = [PHP_BINARY];
                // Native Windows PHP keeps optional extensions disabled in php.ini.
                if (PHP_OS_FAMILY === 'Windows') {
                    $command = [...$command, '-d', 'extension=intl', '-d', 'extension=pdo_pgsql'];
                }
                $process = new Process([...$command, base_path('tests/Support/checkout-worker.php'), $gateFile, $key, base64_encode(json_encode($payload, JSON_THROW_ON_ERROR))], base_path(), $environment, timeout: 30);
                $process->start();
                $processes[] = $process;
            }

            $deadline = microtime(true) + 15;
            while (count(array_filter($processes, fn (Process $process) => str_contains($process->getOutput(), 'READY'))) !== 2) {
                foreach ($processes as $process) {
                    if (! $process->isRunning()) {
                        $this->fail($process->getErrorOutput().$process->getOutput());
                    }
                }
                if (microtime(true) >= $deadline) {
                    $this->fail('Workers did not reach the barrier.');
                }
                usleep(10000);
            }

            touch($gateFile);
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
                $lines = preg_split('/\R/', trim($process->getOutput()));
                $results[] = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            if (is_file($gateFile)) {
                unlink($gateFile);
            }
            rmdir($directory);
        }
    }

    private function statuses(array $results): array
    {
        $statuses = array_column($results, 'status');
        sort($statuses);

        return $statuses;
    }

    private function product(int $stock): Product
    {
        $category = Category::create(['name' => 'Concurrency', 'slug' => 'concurrency', 'is_active' => true]);

        return Product::create([
            'category_id' => $category->id, 'name' => 'One item', 'slug' => 'one-item', 'sku' => 'RACE-1',
            'purchase_price' => 500, 'selling_price' => 2000, 'stock' => $stock, 'track_stock' => true, 'status' => 'active',
        ]);
    }

    private function payload(Product $product): array
    {
        return [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'customer_name' => 'Race Test', 'customer_email' => 'race@example.test', 'customer_phone' => '+38267123456',
            'shipping_address' => 'Test Street 1', 'shipping_city' => 'Podgorica', 'shipping_postal_code' => '81000',
            'shipping_country' => 'ME', 'payment_method' => 'cash_on_delivery',
        ];
    }
}
