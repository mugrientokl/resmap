<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class BackupControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_restore_a_zip_backup_from_the_backup_list(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('backups/database-2026-10-20_02-00-00.zip', 'verified archive');

        $administrator = User::factory()->create(['rol' => 'Administrador']);

        Artisan::shouldReceive('call')
            ->once()
            ->with('app:restore-database-backup', [
                'filename' => 'database-2026-10-20_02-00-00.zip',
                '--force' => true,
            ])
            ->andReturn(0);

        $this->actingAs($administrator)
            ->get(route('backups.index'))
            ->assertOk()
            ->assertSee('Restaurar')
            ->assertSee('Advertencia 1 de 2')
            ->assertSee('Advertencia 2 de 2')
            ->assertSee('data-backup-filename="database-2026-10-20_02-00-00.zip"', false)
            ->assertSee('id="backup-restore-confirmation" name="confirmation" form="backup-restore-form"', false)
            ->assertDontSee('if (event.target === modal)', false);

        $this->actingAs($administrator)
            ->post(route('backups.restore', 'database-2026-10-20_02-00-00.zip'), [
                'confirmation' => 'RESTAURAR',
            ])
            ->assertRedirect(route('backups.index'))
            ->assertSessionHas('success');
    }

    public function test_legacy_backups_are_downloadable_but_cannot_be_restored_from_the_screen(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('backups/database-2026-09-13_00-03-36.sql', 'legacy dump');

        $administrator = User::factory()->create(['rol' => 'Administrador']);

        $this->actingAs($administrator)
            ->get(route('backups.index'))
            ->assertOk()
            ->assertSee('Formato antiguo: solo descarga')
            ->assertDontSee('data-backup-filename="database-2026-09-13_00-03-36.sql"');

        $this->actingAs($administrator)
            ->post(route('backups.restore', 'database-2026-09-13_00-03-36.sql'), [
                'confirmation' => 'RESTAURAR',
            ])
            ->assertNotFound();
    }

    public function test_restoration_requires_the_exact_confirmation_text(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('backups/database-2026-10-20_02-00-00.zip', 'verified archive');

        $administrator = User::factory()->create(['rol' => 'Administrador']);

        Artisan::shouldReceive('call')->never();

        $this->actingAs($administrator)
            ->from(route('backups.index'))
            ->post(route('backups.restore', 'database-2026-10-20_02-00-00.zip'), [
                'confirmation' => 'RESTORE',
            ])
            ->assertRedirect(route('backups.index'))
            ->assertSessionHasErrors('confirmation');
    }

    public function test_a_failed_restore_displays_an_explicit_error_instead_of_success(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('backups/database-2026-10-20_02-00-00.zip', 'verified archive');

        $administrator = User::factory()->create(['rol' => 'Administrador']);

        Artisan::shouldReceive('call')
            ->once()
            ->andReturn(1);

        $this->actingAs($administrator)
            ->post(route('backups.restore', 'database-2026-10-20_02-00-00.zip'), [
                'confirmation' => 'RESTAURAR',
            ])
            ->assertRedirect(route('backups.index'))
            ->assertSessionHas('error')
            ->assertSessionMissing('success');
    }

    public function test_a_restore_exception_is_logged_and_shown_as_a_recoverable_error(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('backups/database-2026-10-20_02-00-00.zip', 'verified archive');

        $administrator = User::factory()->create(['rol' => 'Administrador']);

        Artisan::shouldReceive('call')
            ->once()
            ->andThrow(new RuntimeException('Database client failed.'));
        Log::shouldReceive('error')->once();

        $this->actingAs($administrator)
            ->post(route('backups.restore', 'database-2026-10-20_02-00-00.zip'), [
                'confirmation' => 'RESTAURAR',
            ])
            ->assertRedirect(route('backups.index'))
            ->assertSessionHas('error')
            ->assertSessionMissing('success');
    }

    public function test_non_administrators_cannot_restore_backups(): void
    {
        $seller = User::factory()->create(['rol' => 'Vendedor']);

        $this->actingAs($seller)
            ->post(route('backups.restore', 'database-2026-10-20_02-00-00.zip'), [
                'confirmation' => 'RESTAURAR',
            ])
            ->assertForbidden();
    }
}
