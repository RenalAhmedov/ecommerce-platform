<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\FeatureTestCase;

class PasswordResetTest extends FeatureTestCase
{
    private const GENERIC_STATUS = 'If an admin account exists for this email, a reset link is on its way.';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $this->get('/forgot-password')->assertOk();
    }

    public function test_admins_receive_a_reset_link(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post('/forgot-password', ['email' => $admin->email])
            ->assertSessionHas('status', self::GENERIC_STATUS);

        Notification::assertSentTo($admin, ResetPassword::class);
    }

    public function test_customers_and_unknown_emails_get_the_same_reply_but_no_link(): void
    {
        $customer = User::factory()->create();

        $this->post('/forgot-password', ['email' => $customer->email])
            ->assertSessionHas('status', self::GENERIC_STATUS);
        $this->post('/forgot-password', ['email' => 'nobody@example.test'])
            ->assertSessionHas('status', self::GENERIC_STATUS);

        Notification::assertNothingSent();
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post('/forgot-password', ['email' => $admin->email]);

        Notification::assertSentTo($admin, ResetPassword::class, function (ResetPassword $notification) {
            $this->get('/reset-password/'.$notification->token)->assertOk();

            return true;
        });
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post('/forgot-password', ['email' => $admin->email]);

        Notification::assertSentTo($admin, ResetPassword::class, function (ResetPassword $notification) use ($admin) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $admin->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });

        $this->assertTrue(Hash::check('new-password', $admin->refresh()->password));
    }
}
