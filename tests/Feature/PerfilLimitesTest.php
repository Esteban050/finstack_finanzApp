<?php

namespace Tests\Feature;

use App\Models\PerfilTributario;
use App\Models\Usuario;
use App\Services\SeguridadSocialService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsaBaseLocal;
use Tests\TestCase;

/**
 * Perfil financiero: edad y teléfono en el límite (CA-008.3, RN-008.1),
 * middleware perfil.completo (CA-008.1) y vigencia del perfil por periodo (RN-008.6).
 */
class PerfilLimitesTest extends TestCase
{
    use UsaBaseLocal;

    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->iniciarBaseLocal(); // hoy = 22/11/2026
        $this->usuario = $this->crearUsuario(['Contratista por prestación de servicios']);
        $this->entrarComo($this->usuario);
    }

    protected function tearDown(): void
    {
        $this->cerrarBaseLocal();
        parent::tearDown();
    }

    private function guardar(array $cambios)
    {
        return $this->from(route('profile.show'))->patch(route('profile.update'), array_merge([
            'nombres' => 'Prueba', 'apellidos' => 'Automatica', 'fecha_nacimiento' => '1990-01-01', 'ciudad_residencia' => 'Medellín',
            'ultimos_digitos_nit' => '07', 'personas_a_cargo' => 0, 'ocupaciones' => ['Contratista por prestación de servicios'],
            'clase_riesgo_arl' => 1, 'responsable_iva' => 0,
        ], $cambios));
    }

    public static function edades(): array
    {
        return [
            'cumple 18 hoy (22/11/2008): acepta' => ['2008-11-22', true],
            '17 años y 364 días (23/11/2008): rechaza' => ['2008-11-23', false],
        ];
    }

    #[DataProvider('edades')]
    public function test_edad_en_el_limite(string $nacimiento, bool $acepta): void
    {
        $r = $this->guardar(['fecha_nacimiento' => $nacimiento]);
        $acepta ? $r->assertSessionHas('status', 'Perfil actualizado')
                : $r->assertSessionHasErrors(['fecha_nacimiento' => 'Debes ser mayor de edad']);
    }

    public static function telefonos(): array
    {
        return ['9 dígitos' => ['300123456', false], '10 dígitos' => ['3001234567', true], '11 dígitos' => ['30012345678', false]];
    }

    #[DataProvider('telefonos')]
    public function test_telefono_en_el_limite(string $telefono, bool $acepta): void
    {
        $r = $this->guardar(['telefono' => $telefono]);
        $acepta ? $r->assertSessionHas('status', 'Perfil actualizado')
                : $r->assertSessionHasErrors(['telefono' => 'El teléfono debe tener exactamente 10 dígitos.']);
    }

    public static function modulos(): array
    {
        return ['seguridad social' => ['seguridad-social.index'], 'renta' => ['renta.index']];
    }

    #[DataProvider('modulos')]
    public function test_sin_perfil_completo_el_modulo_envia_a_completar_el_perfil(string $ruta): void
    {
        // Sin perfil
        $sinPerfil = $this->crearUsuario(null);
        $this->entrarComo($sinPerfil);
        $this->get(route($ruta))->assertRedirect(route('profile.show'))
            ->assertSessionHasErrors(['perfil' => 'Completa tu perfil financiero para usar este módulo']);
        $this->getJson(route($ruta))->assertForbidden()->assertJsonPath('mensaje', 'Completa tu perfil financiero para usar este módulo');

        // (Un perfil sin ocupaciones no puede existir: la base lo impide con perfiles_tributarios_ocupaciones_check.)

        // Vecino: con ocupación entra
        $this->entrarComo($this->usuario);
        $this->assertNotSame(route('profile.show'), $this->get(route($ruta))->headers->get('Location'));
    }

    public function test_cada_periodo_usa_la_version_del_perfil_vigente(): void
    {
        // Pasa a pensionado desde el 15/10/2026.
        $this->guardar(['ocupaciones' => ['Contratista por prestación de servicios', 'Pensionado'], 'vigente_desde' => '2026-10-15'])
            ->assertSessionHas('status', 'Perfil actualizado');

        $periodo = fn (string $mes) => SeguridadSocialService::perfilDelPeriodo($this->usuario->id, CarbonImmutable::parse($mes));
        $this->assertNotContains('Pensionado', $periodo('2026-09-01')->ocupaciones);
        $this->assertContains('Pensionado', $periodo('2026-10-01')->ocupaciones);
        $this->assertContains('Pensionado', $periodo('2026-11-01')->ocupaciones);
        $this->assertSame(2, PerfilTributario::where('usuario_id', $this->usuario->id)->count());
    }
}
