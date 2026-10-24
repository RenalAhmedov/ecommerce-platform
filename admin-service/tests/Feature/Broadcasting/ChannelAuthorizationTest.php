<?php

namespace Tests\Feature\Broadcasting;

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Testing\TestResponse;
use Tests\Feature\FeatureTestCase;

class ChannelAuthorizationTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        // Channels are registered on the broadcaster at boot (the tests' null one), so again on this one.
        Broadcast::purge('reverb');
        require base_path('routes/channels.php');
    }

    private function authorize(string $channel): TestResponse
    {
        return $this->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-{$channel}"]);
    }

    public function test_admins_may_listen_to_order_activity(): void
    {
        $signature = hash_hmac('sha256', '1234.5678:private-admin.orders', 'test-secret');

        $this->actingAs(User::factory()->admin()->create())
            ->authorize('admin.orders')
            ->assertOk()
            ->assertJson(['auth' => "test-key:{$signature}"]);
    }

    public function test_customers_and_guests_may_not(): void
    {
        $this->actingAs(User::factory()->create())->authorize('admin.orders')->assertRedirect(route('login'));
        $this->assertGuest();

        $this->authorize('admin.orders')->assertRedirect(route('login'));
    }

    public function test_unknown_channels_are_refused(): void
    {
        $this->actingAs(User::factory()->admin()->create())->authorize('admin.secrets')->assertForbidden();
    }
}
