<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoriaValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_validation_error_redirects_with_reason(): void
    {
        $this->actingAs(User::factory()->create(['rol' => 'Administrador']));

        $response = $this->from('/categorias')->post(route('categorias.store'), [
            'form_type' => 'create',
            'nombre_categoria' => '12345',
            'descripcion' => 'Descripción de prueba',
        ]);

        $response->assertRedirect('/categorias');
        $response->assertSessionHasErrors('nombre_categoria');
        $response->assertSessionHas('validation_error', 'El nombre de la categoría no puede contener únicamente números; debe incluir al menos una letra.');
    }

    public function test_edit_validation_error_keeps_category_id_for_modal(): void
    {
        $this->actingAs(User::factory()->create(['rol' => 'Administrador']));

        $categoria = Categoria::create([
            'nombre_categoria' => 'Motor',
            'descripcion' => 'Repuestos de motor',
        ]);

        $response = $this->from('/categorias')->put(route('categorias.update', $categoria->id_categoria), [
            'form_type' => 'edit',
            'categoria_id' => $categoria->id_categoria,
            'nombre_categoria' => '12345',
            'descripcion' => 'Descripción actualizada',
        ]);

        $response->assertRedirect('/categorias');
        $response->assertSessionHasErrors('nombre_categoria');
        $response->assertSessionHas('validation_error', 'El nombre de la categoría no puede contener únicamente números; debe incluir al menos una letra.');
        $response->assertSessionHas('_old_input.categoria_id', $categoria->id_categoria);
    }
}
