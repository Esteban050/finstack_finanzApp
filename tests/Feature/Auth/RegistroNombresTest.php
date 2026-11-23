<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * RN-001.1: nombres y apellidos obligatorios, de 2 a 60 caracteres,
 * solo letras y espacios. El servidor es la autoridad.
 */
class RegistroNombresTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Igual que las demás pruebas que usan la base local de PostgreSQL,
        // dentro de una transacción que se revierte al terminar.
        config(['database.default' => 'pgsql', 'database.connections.pgsql.database' => 'bd_finanzApp']);
        DB::purge('pgsql');
        DB::connection('pgsql')->beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::connection('pgsql')->rollBack();
        parent::tearDown();
    }

    private function datos(array $cambios = []): array
    {
        return array_merge([
            'nombres' => 'María José',
            'apellidos' => 'Gómez Ruiz',
            'tipo_documento' => 'CC',
            'numero_documento' => (string) random_int(10000000, 99999999),
            'correo' => 'registro.' . Str::lower(Str::random(8)) . '@scfp.test',
            'password' => 'ClaveSegura#2026',
            'password_confirmation' => 'ClaveSegura#2026',
            'acepta_politica' => '1',
        ], $cambios);
    }

    public function test_rechaza_nombres_y_apellidos_de_un_caracter(): void
    {
        $this->post(route('register'), $this->datos(['nombres' => 'A', 'apellidos' => 'B']))
            ->assertSessionHasErrors([
                'nombres' => 'Los nombres deben tener mínimo 2 caracteres.',
                'apellidos' => 'Los apellidos deben tener mínimo 2 caracteres.',
            ]);
    }

    public function test_rechaza_nombres_y_apellidos_de_61_caracteres(): void
    {
        $this->post(route('register'), $this->datos(['nombres' => str_repeat('Ñ', 61), 'apellidos' => str_repeat('Á', 61)]))
            ->assertSessionHasErrors([
                'nombres' => 'Los nombres deben tener máximo 60 caracteres.',
                'apellidos' => 'Los apellidos deben tener máximo 60 caracteres.',
            ]);
    }

    public function test_rechaza_nombres_y_apellidos_vacios_o_con_numeros(): void
    {
        $this->post(route('register'), $this->datos(['nombres' => '', 'apellidos' => '']))
            ->assertSessionHasErrors(['nombres' => 'Los nombres son obligatorios.', 'apellidos' => 'Los apellidos son obligatorios.']);

        $this->post(route('register'), $this->datos(['nombres' => 'Ana2', 'apellidos' => 'Gómez3']))
            ->assertSessionHasErrors([
                'nombres' => 'Los nombres solo deben contener letras y espacios.',
                'apellidos' => 'Los apellidos solo deben contener letras y espacios.',
            ]);
    }

    public function test_acepta_2_y_60_caracteres(): void
    {
        $this->post(route('register'), $this->datos(['nombres' => 'Al', 'apellidos' => str_repeat('Ñ', 60)]))
            ->assertSessionHasNoErrors();

        $this->post(route('register'), $this->datos(['nombres' => str_repeat('á', 60), 'apellidos' => 'Ru']))
            ->assertSessionHasNoErrors();
    }

    public function test_numero_de_documento_de_5_a_15_caracteres(): void
    {
        $this->post(route('register'), $this->datos(['numero_documento' => '1234']))
            ->assertSessionHasErrors(['numero_documento' => 'El número de documento debe tener mínimo 5 caracteres.']);

        $this->post(route('register'), $this->datos(['numero_documento' => str_repeat('1', 16)]))
            ->assertSessionHasErrors(['numero_documento' => 'El número de documento debe tener máximo 15 caracteres.']);

        $this->post(route('register'), $this->datos(['numero_documento' => '12.345']))
            ->assertSessionHasErrors(['numero_documento' => 'El número de documento solo puede tener letras y números, sin puntos, guiones ni espacios.']);

        $this->post(route('register'), $this->datos(['numero_documento' => (string) random_int(10000, 99999)]))
            ->assertSessionHasNoErrors();

        $this->post(route('register'), $this->datos(['numero_documento' => (string) random_int(100000000000000, 999999999999999)]))
            ->assertSessionHasNoErrors();
    }
}
