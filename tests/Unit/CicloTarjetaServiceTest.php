<?php

namespace Tests\Unit;

use App\Services\CicloTarjetaService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class CicloTarjetaServiceTest extends TestCase
{
    private function f(string $fecha): CarbonImmutable
    {
        return CarbonImmutable::parse($fecha);
    }

    public function test_compra_hasta_el_corte_entra_en_ese_extracto_y_despues_en_el_siguiente(): void
    {
        $this->assertSame('2026-11-15', CicloTarjetaService::corteDeCompra($this->f('2026-11-15'), 15)->toDateString());
        $this->assertSame('2026-11-15', CicloTarjetaService::corteDeCompra($this->f('2026-11-03'), 15)->toDateString());
        $this->assertSame('2026-12-15', CicloTarjetaService::corteDeCompra($this->f('2026-11-16'), 15)->toDateString());
        $this->assertSame('2027-01-15', CicloTarjetaService::corteDeCompra($this->f('2026-12-20'), 15)->toDateString());
    }

    public function test_el_pago_es_el_primer_dia_de_pago_posterior_al_corte(): void
    {
        $this->assertSame('2026-12-05', CicloTarjetaService::pagoDeCorte($this->f('2026-11-15'), 5)->toDateString());
        $this->assertSame('2026-11-28', CicloTarjetaService::pagoDeCorte($this->f('2026-11-15'), 28)->toDateString());
        $this->assertSame('2026-12-15', CicloTarjetaService::pagoDeCorte($this->f('2026-11-15'), 15)->toDateString());
    }

    public function test_un_dia_mayor_al_mes_se_ajusta_al_ultimo_dia(): void
    {
        $this->assertSame('2027-02-28', CicloTarjetaService::fechaDelMes($this->f('2027-02-10'), 31)->toDateString());
        $this->assertSame('2027-03-31', CicloTarjetaService::corteDeCompra($this->f('2027-03-01'), 31)->toDateString());
    }

    public function test_proxima_fecha_incluye_hoy(): void
    {
        $this->assertSame('2026-11-26', CicloTarjetaService::proximaFecha(26, $this->f('2026-11-26'))->toDateString());
        $this->assertSame('2026-12-10', CicloTarjetaService::proximaFecha(10, $this->f('2026-11-26'))->toDateString());
    }
}
