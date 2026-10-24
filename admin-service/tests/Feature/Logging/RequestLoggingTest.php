<?php

namespace Tests\Feature\Logging;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mockery;
use Tests\Feature\FeatureTestCase;

class RequestLoggingTest extends FeatureTestCase
{
    public function test_every_response_carries_a_request_id(): void
    {
        $id = $this->get('/login')->assertOk()->headers->get('X-Request-Id');

        $this->assertTrue(Str::isUuid($id));
    }

    public function test_a_valid_request_id_from_a_proxy_is_kept_and_a_bad_one_replaced(): void
    {
        $this->withHeader('X-Request-Id', 'nginx-0123456789abcdef')->get('/login')->assertHeader('X-Request-Id', 'nginx-0123456789abcdef');

        $injected = $this->withHeader('X-Request-Id', "evil\nINFO fake log line")->get('/login')->headers->get('X-Request-Id');
        $this->assertTrue(Str::isUuid($injected));
    }

    public function test_each_request_is_logged_and_the_admin_is_attached_to_every_log_line(): void
    {
        Log::spy();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/dashboard')->assertOk();

        Log::shouldHaveReceived('info')->with('HTTP request', Mockery::on(fn (array $context) => $context['method'] === 'GET'
            && $context['path'] === '/dashboard'
            && $context['status'] === 200
            && is_float($context['duration_ms'])));
        $this->assertSame($admin->id, Context::get('user_id'));
        $this->assertTrue(Str::isUuid(Context::get('request_id')));
    }

    /** Nginx sits on Docker's private network; a public caller can't fake its address with the header. */
    public function test_the_client_ip_comes_from_nginx_but_only_from_nginx(): void
    {
        Log::spy();

        $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])->withHeader('X-Forwarded-For', '203.0.113.9')->get('/login');
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])->withHeader('X-Forwarded-For', '203.0.113.9')->get('/login');

        Log::shouldHaveReceived('info')->with('HTTP request', Mockery::on(fn (array $context) => $context['ip'] === '203.0.113.9'))->once();
        Log::shouldHaveReceived('info')->with('HTTP request', Mockery::on(fn (array $context) => $context['ip'] === '198.51.100.7'))->once();
    }

    public function test_the_health_check_is_not_logged(): void
    {
        Log::spy();

        $this->get('/up')->assertOk();

        Log::shouldNotHaveReceived('info', ['HTTP request', Mockery::any()]);
    }

    public function test_business_events_are_logged(): void
    {
        Log::spy();
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);

        $this->actingAs($admin)->patch("/orders/{$order->id}/status", ['status' => 'processing']);
        $this->post('/logout');
        $this->post('/login', ['email' => $admin->email, 'password' => 'wrong-password']);

        Log::shouldHaveReceived('info')->with('Order status changed', Mockery::on(fn (array $context) => $context['order_id'] === $order->id
            && $context['from'] === 'paid'
            && $context['to'] === 'processing'));
        Log::shouldHaveReceived('warning')->with('Admin login failed', Mockery::on(fn (array $context) => $context['email'] === $admin->email));
    }
}
