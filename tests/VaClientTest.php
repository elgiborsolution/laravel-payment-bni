<?php

namespace ESolution\BNIPayment\Tests;

use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Http;
use ESolution\BNIPayment\BNIPaymentServiceProvider;
use ESolution\BNIPayment\Clients\BniVaClient;
use ESolution\BNIPayment\Exceptions\BniApiException;
use ESolution\BNIPayment\Models\BniPaymentLog;

class BniVaClientTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [BNIPaymentServiceProvider::class];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('bni.hostname', 'api.example.test');
        $app['config']->set('bni.port', 443);
        $app['config']->set('bni.origin', 'test-origin');
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function test_create_billing_success_is_logged()
    {
        Http::fake(['*' => Http::response(['status' => '000', 'data' => ['virtual_account' => '8001', 'trx_id' => 'INV1']], 200)]);
        $client = $this->app->make(BniVaClient::class);
        $res = $client->createBilling([
            'type' => 'createbilling',
            'client_id' => '320',
            'trx_id' => 'INV1',
            'trx_amount' => '100000',
            'billing_type' => 'c',
            'customer_name' => 'Tester',
        ]);
        $this->assertEquals('000', $res['status']);
        $this->assertDatabaseCount('bni_api_calls', 1);
        $log = BniPaymentLog::first();
        $this->assertEquals('va', $log->channel);
        $this->assertEquals('/customer/ecollection/create', $log->endpoint);
        $this->assertEquals(200, $log->http_status);
    }

    public function test_create_billing_error_throws_exception_and_logged()
    {
        Http::fake(['*' => Http::response(['status' => '101'], 200)]);
        $this->expectException(BniApiException::class);

        $client = $this->app->make(BniVaClient::class);
        try {
            $client->createBilling([
                'type' => 'createbilling',
                'client_id' => '320',
                'trx_id' => 'INV404',
                'trx_amount' => '100000',
                'billing_type' => 'c',
                'customer_name' => 'Tester',
            ]);
        } catch (BniApiException $e) {
            $this->assertEquals('101', $e->bniCode);
            $this->assertDatabaseCount('bni_api_calls', 1);
            throw $e;
        }
    }

    public function test_update_billing_success_is_logged()
    {
        Http::fake(['*' => Http::response(['status' => '000', 'data' => ['virtual_account' => '8320', 'trx_id' => 'INV1']], 200)]);
        $client = $this->app->make(BniVaClient::class);
        $res = $client->updateBilling([
            'client_id' => '320',
            'trx_id' => 'INV1',
            'trx_amount' => '100000',
            'customer_name' => 'Tester',
            'type' => 'updatebilling',
        ]);
        $this->assertEquals('000', $res['status']);
        $this->assertDatabaseCount('bni_api_calls', 1);
        $log = BniPaymentLog::first();
        $this->assertEquals('/customer/ecollection/update', $log->endpoint);
        $this->assertEquals('PUT', $log->method);
    }

    public function test_update_billing_error_throws_exception_and_logged()
    {
        Http::fake(['*' => Http::response(['status' => '101'], 200)]);
        $this->expectException(BniApiException::class);
        $client = $this->app->make(BniVaClient::class);
        try {
            $client->updateBilling([
                'client_id' => '320',
                'trx_id' => 'INV404',
                'trx_amount' => '100000',
                'customer_name' => 'Tester',
                'type' => 'updatebilling',
            ]);
        } catch (BniApiException $e) {
            $this->assertEquals('101', $e->bniCode);
            $this->assertDatabaseCount('bni_api_calls', 1);
            throw $e;
        }
    }

    public function test_inquiry_billing_success_is_logged()
    {
        Http::fake(['*' => Http::response(['status' => '000', 'data' => ['trx_id' => 'INV1', 'virtual_account' => '8001']], 200)]);
        $client = $this->app->make(BniVaClient::class);
        $res = $client->inquiryBilling('INV1');
        $this->assertEquals('000', $res['status']);
        $this->assertDatabaseCount('bni_api_calls', 1);
        $log = BniPaymentLog::first();
        $this->assertEquals('/customer/ecollection/inquiry', $log->endpoint);
        $this->assertEquals('POST', $log->method);
    }

    public function test_inquiry_billing_error_throws_exception_and_logged()
    {
        Http::fake(['*' => Http::response(['status' => '101'], 200)]);
        $this->expectException(BniApiException::class);
        $client = $this->app->make(BniVaClient::class);
        try {
            $client->inquiryBilling('INV404');
        } catch (BniApiException $e) {
            $this->assertEquals('101', $e->bniCode);
            $this->assertDatabaseCount('bni_api_calls', 1);
            throw $e;
        }
    }
}
