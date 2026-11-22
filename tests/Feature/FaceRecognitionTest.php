<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaceRecognitionTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Face-Access-Secure-Phrase-2026';

    public function test_admin_and_seller_can_manage_their_own_face_profile(): void
    {
        foreach (['Administrador', 'Vendedor'] as $role) {
            $user = User::factory()->create(['rol' => $role]);

            $this->actingAs($user)
                ->get(route('perfil.show'))
                ->assertOk()
                ->assertSee('Reconocimiento facial')
                ->assertSee('Registrar rostro');
        }

        auth()->logout();
        $this->get(route('perfil.show'))->assertRedirect(route('login'));
    }

    public function test_enrollment_requires_consent_and_the_current_password_and_encrypts_descriptor(): void
    {
        $user = User::factory()->create([
            'rol' => 'Vendedor',
            'password' => self::PASSWORD,
        ]);
        $descriptor = $this->descriptor(0.01);

        $this->actingAs($user)
            ->from(route('perfil.show'))
            ->post(route('perfil.rostro.store'), [
                'password' => self::PASSWORD,
                'face_descriptor' => $descriptor,
            ])
            ->assertRedirect(route('perfil.show'))
            ->assertSessionHasErrors('consent');

        $this->from(route('perfil.show'))
            ->post(route('perfil.rostro.store'), [
                'password' => 'incorrect-password',
                'consent' => '1',
                'face_descriptor' => $descriptor,
            ])
            ->assertRedirect(route('perfil.show'))
            ->assertSessionHasErrors('password');

        $this->from(route('perfil.show'))
            ->post(route('perfil.rostro.store'), [
                'password' => self::PASSWORD,
                'consent' => '1',
                'face_descriptor' => $descriptor,
            ])
            ->assertRedirect(route('perfil.show'));

        $user->refresh();
        $encryptedDescriptor = $user->getRawOriginal('face_descriptor');

        $this->assertNotSame(json_encode($descriptor), $encryptedDescriptor);
        $this->assertEquals($descriptor, $user->face_descriptor);
        $this->assertNotNull($user->face_consent_at);
        $this->assertArrayNotHasKey('face_descriptor', $user->toArray());
    }

    public function test_face_login_creates_a_session_with_only_a_matching_descriptor(): void
    {
        $user = $this->userWithFace();

        $this->from(route('login'))
            ->post(route('login.face'), [
                'face_descriptor' => $this->descriptor(0.012),
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'Acceso autorizado.');

        $this->assertAuthenticatedAs($user);
    }

    public function test_face_login_rejects_a_wrong_descriptor(): void
    {
        $user = $this->userWithFace();

        $this->from(route('login'))
            ->post(route('login.face'), [
                'face_descriptor' => $this->descriptor(0.9),
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('face');

        $this->assertGuest();
    }

    public function test_face_login_requires_a_unique_server_side_match(): void
    {
        $descriptor = $this->descriptor(0.01);

        foreach (['Administrador', 'Vendedor'] as $role) {
            $user = User::factory()->create(['rol' => $role]);
            $user->forceFill([
                'face_descriptor' => $descriptor,
                'face_consent_at' => now(),
            ])->save();
        }

        $this->from(route('login'))
            ->post(route('login.face'), [
                'face_descriptor' => $descriptor,
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('face');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_sign_in_with_a_matching_face(): void
    {
        $user = $this->userWithFace(['activo' => false]);

        $this->from(route('login'))
            ->post(route('login.face'), [
                'face_descriptor' => $this->descriptor(0.01),
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('face');

        $this->assertGuest();
    }

    public function test_removing_face_keeps_traditional_password_login_available(): void
    {
        $user = $this->userWithFace();

        $this->actingAs($user)
            ->from(route('perfil.show'))
            ->delete(route('perfil.rostro.destroy'), ['password' => self::PASSWORD])
            ->assertRedirect(route('perfil.show'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'face_descriptor' => null,
            'face_consent_at' => null,
        ]);

        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->post(route('login.store'), [
            'login' => $user->username,
            'password' => self::PASSWORD,
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    private function userWithFace(array $attributes = []): User
    {
        $user = User::factory()->create([
            'rol' => 'Vendedor',
            'password' => self::PASSWORD,
            ...$attributes,
        ]);

        $user->forceFill([
            'face_descriptor' => $this->descriptor(0.01),
            'face_consent_at' => now(),
        ])->save();

        return $user;
    }

    private function descriptor(float $value): array
    {
        return array_fill(0, 128, $value);
    }
}
