<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_sessions_expire_after_inactivity_and_browser_close_with_secure_cookie_flags(): void
    {
        $this->assertSame(15, config('session.lifetime'));
        $this->assertTrue(config('session.expire_on_close'));
        $this->assertTrue(config('session.encrypt'));
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
    }

    public function test_global_control_stylesheet_is_loaded_on_application_and_auth_pages(): void
    {
        $stylesheet = asset('css/global-controls.css');

        foreach ([
            route('home'),
            route('login'),
            route('password.request'),
            route('password.reset', ['token' => 'test-token']),
        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee($stylesheet, false);
        }

        $this->assertFileExists(public_path('css/global-controls.css'));
        $styles = file_get_contents(public_path('css/global-controls.css'));

        $this->assertIsString($styles);
        $this->assertStringContainsString('border-radius: 0.5rem', $styles);
        $this->assertStringContainsString(':not([type="checkbox"])', $styles);
    }

    public function test_seller_login_regenerates_the_session_and_logout_invalidates_it(): void
    {
        $seller = User::factory()->create([
            'rol' => 'Vendedor',
            'password' => 'Vendedor-Seguro-2026-Frase',
        ]);

        $this->get(route('login'))->assertOk();
        $sessionIdBeforeLogin = session()->getId();

        $this->post(route('login.store'), [
            'login' => $seller->username,
            'password' => 'Vendedor-Seguro-2026-Frase',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($seller);
        $this->assertNotSame($sessionIdBeforeLogin, session()->getId());

        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_admin_sign_in_does_not_depend_on_external_services(): void
    {
        $admin = User::factory()->create([
            'rol' => 'Administrador',
            'password' => 'Admin-Secure-Phrase-2026',
        ]);

        $this->post(route('login.store'), [
            'login' => $admin->username,
            'password' => 'Admin-Secure-Phrase-2026',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_failed_login_attempts_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), [
                'login' => 'admin',
                'password' => 'incorrect-password',
            ])->assertRedirect();
        }

        $this->post(route('login.store'), [
            'login' => 'admin',
            'password' => 'incorrect-password',
        ])->assertTooManyRequests();
    }

    public function test_password_reset_requires_twelve_characters_and_its_token_is_single_use(): void
    {
        $user = User::factory()->create(['rol' => 'Vendedor']);
        DB::table('sessions')->insert([
            'id' => 'password-reset-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);
        $token = Password::createToken($user);

        $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertSessionHasErrors('password');

        $password = 'New-Secure-Phrase-For-Testing-2026';
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check($password, $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'password-reset-session']);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertSessionHasErrors('email');
    }
}
